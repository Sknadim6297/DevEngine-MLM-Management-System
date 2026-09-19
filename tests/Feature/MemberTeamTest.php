<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_members_are_only_immediate_sponsors(): void
    {
        $root = $this->createMember('ST100001', 'Root Member', 'ST666666', 'active');
        $direct = $this->createMember('ST100002', 'Direct One', $root->member_id, 'active');
        $this->createMember('ST100003', 'Direct Two', $root->member_id, 'inactive');
        $this->createMember('ST100004', 'Grandchild', $direct->member_id, 'active');
        $this->memberSession($root);

        $response = $this->get(route('member.team.direct'));

        $response
            ->assertOk()
            ->assertSeeText('Direct Members')
            ->assertSeeText('2')
            ->assertSeeText('Direct One')
            ->assertSeeText('Direct Two')
            ->assertDontSeeText('Grandchild')
            ->assertDontSee('<td>Root Member</td>', false);
    }

    public function test_whole_team_contains_all_levels_and_excludes_root(): void
    {
        $root = $this->createMember('ST100001', 'Root Member', 'ST666666', 'active');
        $direct = $this->createMember('ST100002', 'Direct One', $root->member_id, 'active');
        $second = $this->createMember('ST100003', 'Second Level', $direct->member_id, 'inactive');
        $this->createMember('ST100004', 'Third Level', $second->member_id, 'active');
        $this->memberSession($root);

        $response = $this->get(route('member.team.whole'));

        $response
            ->assertOk()
            ->assertSeeText('Whole Team')
            ->assertSeeText('Direct One')
            ->assertSeeText('Second Level')
            ->assertSeeText('Third Level')
            ->assertSeeText('Level 1')
            ->assertSeeText('Level 2')
            ->assertSeeText('Level 3')
            ->assertDontSee('<td>Root Member</td>', false);
    }

    public function test_unrelated_team_and_tampered_query_are_not_visible(): void
    {
        $root = $this->createMember('ST100001', 'Root Member', 'ST666666', 'active');
        $otherRoot = $this->createMember('ST100010', 'Other Root', 'ST666666', 'active');
        $this->createMember('ST100002', 'Root Child', $root->member_id, 'inactive');
        $this->createMember('ST100011', 'Other Child', $otherRoot->member_id, 'active');
        $this->memberSession($root);

        $this->get(route('member.team.whole', ['member_id' => $otherRoot->member_id]))
            ->assertOk()
            ->assertSeeText('Root Child')
            ->assertDontSeeText('Other Root')
            ->assertDontSeeText('Other Child');
    }

    public function test_counts_include_inactive_descendants_and_admin_sponsor_is_not_a_member(): void
    {
        $root = $this->createMember('ST100001', 'Root Member', 'ST666666', 'active');
        $direct = $this->createMember('ST100002', 'Direct One', $root->member_id, 'inactive');
        $this->createMember('ST100003', 'Direct Two', $root->member_id, 'inactive');
        $this->createMember('ST100004', 'Nested One', $direct->member_id, 'inactive');
        $this->memberSession($root);

        $this->get(route('member.team.whole'))
            ->assertOk()
            ->assertSeeText('Direct Members')
            ->assertSeeText('Whole Team')
            ->assertSeeText('Direct One')
            ->assertSeeText('Direct Two')
            ->assertSeeText('Nested One')
            ->assertSeeText('Inactive')
            ->assertDontSeeText('Admin');
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }

    private function createMember(string $memberId, string $name, string $sponsorId, string $status): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => $sponsorId === 'ST666666' ? 'Admin' : 'Sponsor',
            'member_name' => $name,
            'mobile_no' => '987654' . substr($memberId, -4),
            'email' => strtolower($memberId) . '@example.com',
            'status' => $status,
        ]);
    }
}
