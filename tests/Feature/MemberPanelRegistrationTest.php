<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPanelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_own_member_id_as_default_sponsor(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $this->memberSession($member);

        $this->get(route('member.registration'))
            ->assertOk()
            ->assertSee('value="ST100001"', false)
            ->assertSee('value="Rahul Das"', false);
    }

    public function test_member_can_register_with_a_different_existing_sponsor(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $sponsor = $this->createMember('ST100002', 'Other Sponsor', 'sponsor@example.com');
        $this->memberSession($member);

        $this->post(route('member.registration.store'), [
            'member_name' => 'New Member',
            'sponsor_id' => $sponsor->member_id,
            'sponsor_name' => 'Forged Name',
            'email' => 'new@example.com',
            'mobile_no' => '9876543212',
        ])->assertRedirect(route('member.registration'));

        $this->assertDatabaseHas('members', [
            'member_name' => 'New Member',
            'sponsor_id' => $sponsor->member_id,
            'sponsor_name' => 'Other Sponsor',
            'email' => 'new@example.com',
            'mobile_no' => '9876543212',
            'status' => 'inactive',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.members.inactive'))
            ->assertOk()
            ->assertSeeText('New Member')
            ->assertSeeText($sponsor->member_id)
            ->assertSeeText('Other Sponsor')
            ->assertSeeText('new@example.com')
            ->assertSeeText('9876543212');
    }

    public function test_invalid_sponsor_is_rejected(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $this->memberSession($member);

        $this->from(route('member.registration'))
            ->post(route('member.registration.store'), [
                'member_name' => 'New Member',
                'sponsor_id' => 'ST999999',
                'email' => 'new@example.com',
                'mobile_no' => '9876543212',
            ])
            ->assertRedirect(route('member.registration'))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->assertDatabaseMissing('members', ['email' => 'new@example.com']);
    }

    public function test_sponsor_lookup_returns_database_name(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $sponsor = $this->createMember('ST100002', 'Other Sponsor', 'sponsor@example.com');
        $this->memberSession($member);

        $this->getJson(route('member.registration.check-sponsor', ['sponsor_id' => $sponsor->member_id]))
            ->assertOk()
            ->assertJson([
                'exists' => true,
                'sponsor_name' => 'Other Sponsor',
            ]);
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }

    private function createMember(string $memberId, string $name, string $email): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9876543210',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
