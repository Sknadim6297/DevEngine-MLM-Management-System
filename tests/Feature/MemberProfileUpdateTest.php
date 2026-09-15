<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function member(): Member
    {
        return Member::create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Rahul Das',
            'wallet_address' => '0xoriginal',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'password' => bcrypt('old-password'),
            'status' => 'inactive',
        ]);
    }

    public function test_profile_update_page_starts_without_a_member(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->get(route('admin.members.update'))
            ->assertOk()
            ->assertDontSee($member->member_id)
            ->assertDontSee($member->member_name)
            ->assertDontSee($member->email)
            ->assertDontSee('old-password');
    }

    public function test_fetch_returns_requested_member_details_without_password(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->getJson(route('admin.members.fetch-details', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJson([
                'member_id' => $member->member_id,
                'name' => $member->member_name,
                'email' => $member->email,
            ])
            ->assertJsonMissing(['password' => $member->password]);
    }

    public function test_missing_member_returns_member_not_found(): void
    {
        $this->signIn();

        $this->getJson(route('admin.members.fetch-details', ['member_id' => 'ST999999']))
            ->assertNotFound()
            ->assertJson(['message' => 'Member not found.']);
    }

    public function test_blank_password_keeps_existing_password_when_profile_is_updated(): void
    {
        $this->signIn();
        $member = $this->member();
        $originalPassword = $member->password;

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Updated',
            'wallet_address' => '0xupdated',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'password' => '',
        ])->assertRedirect(route('admin.members.update'));

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Updated',
            'password' => $originalPassword,
        ]);
    }
}
