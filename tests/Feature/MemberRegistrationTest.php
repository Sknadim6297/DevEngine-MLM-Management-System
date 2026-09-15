<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_registration_creates_record_and_lists_it_in_active_and_inactive_views(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $sponsor = Member::create([
            'member_id' => 'ST110721',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Sk Nadim',
            'wallet_address' => '0x111',
            'mobile_no' => '9876543211',
            'pan_card_no' => 'ABCDE1234A',
            'email' => 'sknadim@example.com',
            'status' => 'inactive',
        ]);

        $this->actingAs(User::first());

        $response = $this->post('/admin/members/store', [
            'sponsor_id' => $sponsor->member_id,
            'sponsor_name' => $sponsor->member_name,
            'member_name' => 'Rahul Das',
            'wallet_address' => '0x5b8...',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'status' => 'inactive',
        ]);

        $response->assertRedirect('/admin/members/registration');

        $this->get('/admin/members/registration')
            ->assertOk()
            ->assertSeeText('ST');

        $this->assertDatabaseHas('members', [
            'email' => 'rahul@example.com',
            'status' => 'inactive',
            'member_name' => 'Rahul Das',
            'pan_card_no' => 'ABCDE1234F',
            'sponsor_id' => $sponsor->member_id,
            'sponsor_name' => $sponsor->member_name,
        ]);

        $member = Member::first();

        $this->get('/admin/members/active')
            ->assertOk()
            ->assertSeeText('No active members found.');

        $this->get('/admin/members/inactive')
            ->assertOk()
            ->assertSeeText('Rahul Das')
            ->assertSeeText($member->member_id);
    }

    public function test_admin_default_sponsor_id_is_accepted_for_registration(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $this->actingAs(User::first());

        $this->post('/admin/members/store', [
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Rahul Das',
            'wallet_address' => '0x5b8...',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
        ])->assertRedirect('/admin/members/registration');

        $this->assertDatabaseHas('members', [
            'email' => 'rahul@example.com',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
        ]);
    }

    public function test_inactive_member_list_shows_pan_card_and_mobile_number_reuse_is_limited_to_three_times(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $this->actingAs(User::first());

        $validMemberNames = ['Member One', 'Member Two', 'Member Three'];

        foreach (range(1, 3) as $index) {
            $this->post('/admin/members/store', [
                'member_name' => $validMemberNames[$index - 1],
                'wallet_address' => '0xabc',
                'mobile_no' => '9876543210',
                'pan_card_no' => 'ABCDE1234' . chr(64 + $index),
                'email' => 'member' . $index . '@example.com',
            ])->assertRedirect('/admin/members/registration');
        }

        $this->post('/admin/members/store', [
            'member_name' => 'Member Four',
            'wallet_address' => '0xabc',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234D',
            'email' => 'member4@example.com',
        ])->assertSessionHasErrors(['mobile_no']);

        $this->get('/admin/members/inactive')
            ->assertOk()
            ->assertSeeText('PAN Card Number');
    }

    public function test_pan_card_number_can_be_reused_up_to_three_times(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $this->actingAs(User::first());

        foreach (range(1, 3) as $index) {
            $names = ['Member One', 'Member Two', 'Member Three'];
            $this->post('/admin/members/store', [
                'member_name' => $names[$index - 1],
                'wallet_address' => '0xabc',
                'mobile_no' => '987654321' . $index,
                'pan_card_no' => 'ABCDE1234F',
                'email' => 'member' . $index . '@example.com',
            ])->assertRedirect('/admin/members/registration');
        }

        $this->post('/admin/members/store', [
            'member_name' => 'Member Four',
            'wallet_address' => '0xabc',
            'mobile_no' => '9876543214',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'member4@example.com',
        ])->assertSessionHasErrors(['pan_card_no']);
    }
}
