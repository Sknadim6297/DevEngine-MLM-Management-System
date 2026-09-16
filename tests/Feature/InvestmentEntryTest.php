<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentEntryTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function member(string $status = 'inactive'): Member
    {
        return Member::create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Rahul Das',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'status' => $status,
        ]);
    }

    public function test_qualifying_investment_creates_active_investment_and_activates_member(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->post(route('admin.investments.store'), [
            'investment_id' => 'INV100001',
            'member_id' => $member->member_id,
            'member_name' => 'Incorrect Name',
            'category' => 'Group A',
            'amount' => 100,
        ])->assertRedirect(route('admin.investments.entry'));

        $this->assertDatabaseHas('investments', [
            'investment_id' => 'INV100001',
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Das',
            'category' => 'Group A',
            'amount' => 100,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'status' => 'active',
        ]);
        $this->get(route('admin.members.active'))->assertSeeText('Rahul Das');
        $this->get(route('admin.investments.active-investments'))
            ->assertSeeText('INV100001')
            ->assertSeeText('Group A');
    }

    public function test_amounts_below_minimum_are_rejected_without_creating_or_activating(): void
    {
        $this->signIn();
        $member = $this->member();

        foreach (['99', '0', '-50'] as $amount) {
            $this->post(route('admin.investments.store'), [
                'investment_id' => 'INV' . $amount,
                'member_id' => $member->member_id,
                'category' => 'Group A',
                'amount' => $amount,
            ])->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseCount('investments', 0);
        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'status' => 'inactive',
        ]);
    }

    public function test_invalid_member_id_is_rejected(): void
    {
        $this->signIn();

        $this->post(route('admin.investments.store'), [
            'investment_id' => 'INV100002',
            'member_id' => 'ST999999',
            'category' => 'Group A',
            'amount' => 150,
        ])->assertSessionHasErrors('member_id');

        $this->assertDatabaseCount('investments', 0);
    }

    public function test_member_lookup_returns_database_name(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->getJson(route('admin.investments.member-lookup', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJson([
                'member_id' => $member->member_id,
                'member_name' => 'Rahul Das',
            ]);
    }

    public function test_already_active_member_remains_active_after_another_valid_investment(): void
    {
        $this->signIn();
        $member = $this->member('active');

        $this->post(route('admin.investments.store'), [
            'investment_id' => 'INV100003',
            'member_id' => $member->member_id,
            'category' => 'Group B',
            'amount' => 150,
        ])->assertRedirect();

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('investments', 1);
    }

    public function test_group_a_and_group_b_categories_are_stored_without_changing_on_reload(): void
    {
        $this->signIn();
        $member = $this->member();

        foreach (['Group A', 'Group B'] as $index => $category) {
            $this->post(route('admin.investments.store'), [
                'investment_id' => 'INV20000' . $index,
                'member_id' => $member->member_id,
                'category' => $category,
                'amount' => 100,
            ])->assertRedirect(route('admin.investments.entry'));

            $this->assertDatabaseHas('investments', [
                'investment_id' => 'INV20000' . $index,
                'category' => $category,
            ]);
        }

        $this->get(route('admin.investments.active-investments'))
            ->assertOk()
            ->assertSeeText('Group A')
            ->assertSeeText('Group B');
    }

    public function test_category_is_required_and_only_group_a_or_group_b_is_accepted(): void
    {
        $this->signIn();
        $member = $this->member();

        foreach ([null, '', 'Plan A', 'group a'] as $index => $category) {
            $this->post(route('admin.investments.store'), [
                'investment_id' => 'INV30000' . $index,
                'member_id' => $member->member_id,
                'category' => $category,
                'amount' => 100,
            ])->assertSessionHasErrors('category');
        }

        $this->assertDatabaseCount('investments', 0);
    }
}
