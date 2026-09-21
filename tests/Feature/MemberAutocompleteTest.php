<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_member_can_search_own_connected_member(): void
    {
        $root = $this->member('ST100051', 'Test Member', 'ST666666');
        $connected = $this->member('ST100000', 'Rahul Das', $root->member_id);
        $this->member('ST100999', 'Unrelated Member', 'ST666666');
        $this->withSession(['member_context_id' => $root->member_id]);

        $this->getJson(route('member.search-members', ['member_id' => 'ST1000']))
            ->assertOk()
            ->assertJsonFragment(['member_id' => $connected->member_id])
            ->assertJsonFragment(['member_name' => $connected->member_name]);
    }

    public function test_connected_downline_member_appears_in_suggestions(): void
    {
        $root = $this->member('ST100051', 'Test Member', 'ST666666');
        $connected = $this->member('ST100000', 'Rahul Das', $root->member_id);
        $otherRoot = $this->member('ST100200', 'Other Root', 'ST666666');
        $unconnected = $this->member('ST100201', 'Outside Member', $otherRoot->member_id);
        $this->withSession(['member_context_id' => $root->member_id]);

        $this->getJson(route('member.search-members', ['member_id' => 'ST100']))
            ->assertOk()
            ->assertJsonFragment(['member_id' => $connected->member_id])
            ->assertJsonMissing(['member_id' => $unconnected->member_id]);
    }

    public function test_unconnected_member_does_not_appear_in_suggestions(): void
    {
        $root = $this->member('ST100051', 'Test Member', 'ST666666');
        $otherRoot = $this->member('ST100200', 'Other Root', 'ST666666');
        $unconnected = $this->member('ST100201', 'Outside Member', $otherRoot->member_id);
        $this->withSession(['member_context_id' => $root->member_id]);

        $this->getJson(route('member.search-members', ['member_id' => 'ST1002']))
            ->assertOk()
            ->assertJsonMissing(['member_id' => $unconnected->member_id]);
    }

    public function test_clicking_a_suggestion_populates_the_selected_member_id(): void
    {
        $root = $this->member('ST100051', 'Test Member', 'ST666666');
        $connected = $this->member('ST100000', 'Rahul Das', $root->member_id);
        $this->withSession(['member_context_id' => $root->member_id]);

        $this->getJson(route('member.search-members', ['member_id' => 'ST1000']))
            ->assertOk()
            ->assertJsonPath('0.member_id', $connected->member_id)
            ->assertJsonPath('0.member_name', $connected->member_name);
    }

    public function test_directly_submitting_unauthorized_member_id_is_rejected(): void
    {
        $root = $this->member('ST100051', 'Test Member', 'ST666666');
        $otherRoot = $this->member('ST100200', 'Other Root', 'ST666666');
        $this->member('ST100201', 'Outside Member', $otherRoot->member_id);
        $this->withSession(['member_context_id' => $root->member_id]);

        $this->getJson(route('member.registration.check-sponsor', ['sponsor_id' => 'ST100201']))
            ->assertOk()
            ->assertJsonPath('exists', false)
            ->assertJsonPath('message', 'Unauthorized sponsor ID.');
    }

    private function member(string $memberId, string $name, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => $name,
            'mobile_no' => '98765' . substr($memberId, -5),
            'pan_card_no' => 'ABCDE' . substr($memberId, -5),
            'email' => strtolower($memberId) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
