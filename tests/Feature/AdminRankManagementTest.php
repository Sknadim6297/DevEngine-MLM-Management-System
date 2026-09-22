<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Models\User;
use App\Services\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRankManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_and_edit_ranks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('admin.ranks.index'))
            ->assertOk()
            ->assertSeeText('Rank Management')
            ->assertSeeText('Silver');

        $this->post(route('admin.ranks.store'), [
            'name' => 'Test Rank',
            'required_full_team_business' => '7000.0000',
            'unlocked_levels' => 5,
            'is_active' => 1,
            'sort_order' => 20,
        ])->assertRedirect(route('admin.ranks.index'));

        $rank = Rank::where('name', 'Test Rank')->firstOrFail();
        $this->put(route('admin.ranks.update', $rank), [
            'name' => 'Test Rank Updated',
            'required_full_team_business' => '8000.0000',
            'unlocked_levels' => 6,
            'is_active' => 0,
            'sort_order' => 20,
        ])->assertRedirect(route('admin.ranks.index'));

        $this->assertDatabaseHas('ranks', [
            'id' => $rank->id,
            'name' => 'Test Rank Updated',
            'required_full_team_business' => '8000.0000',
            'unlocked_levels' => 6,
            'is_active' => 0,
        ]);
    }

    public function test_rank_validation_rejects_duplicate_names_and_invalid_levels(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $silver = Rank::where('name', 'Silver')->firstOrFail();

        $this->post(route('admin.ranks.store'), [
            'name' => $silver->name,
            'required_full_team_business' => '-1',
            'unlocked_levels' => 33,
            'is_active' => 1,
            'sort_order' => $silver->sort_order,
        ])->assertSessionHasErrors(['name', 'required_full_team_business', 'unlocked_levels', 'sort_order']);
    }

    public function test_rank_changes_are_used_dynamically_by_rank_service(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $silver = Rank::where('name', 'Silver')->firstOrFail();
        $silver->update(['required_full_team_business' => '7000.0000', 'unlocked_levels' => 5]);

        $root = $this->member('STRANK001', 'ST666666');
        $child = $this->member('STRANK002', $root->member_id);
        Investment::create([
            'investment_id' => 'INVRANK001',
            'member_id' => $child->member_id,
            'member_name' => $child->member_name,
            'amount' => '6500.0000',
            'status' => 'active',
        ]);

        $result = app(RankService::class)->calculateForMember($root);
        $this->assertNull($result['current_rank']);
        $this->assertSame('7000.0000', $result['next_rank']->required_full_team_business);

        $silver->update(['is_active' => false]);
        $this->assertSame('Gold', app(RankService::class)->calculateForMember($root)['next_rank']->name);
    }

    public function test_admin_can_delete_an_unreferenced_rank(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $rank = Rank::where('name', 'Silver')->firstOrFail();

        $this->delete(route('admin.ranks.destroy', $rank))
            ->assertRedirect(route('admin.ranks.index'))
            ->assertSessionHas('success', 'Rank deleted successfully.');

        $this->assertDatabaseMissing('ranks', ['id' => $rank->id]);
    }

    public function test_rank_cannot_be_deleted_when_referenced_by_members(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $rank = Rank::where('name', 'Silver')->firstOrFail();
        $member = $this->member('STRANKDELETE', 'ST666666');
        $member->update(['rank_id' => $rank->id]);

        $this->delete(route('admin.ranks.destroy', $rank))
            ->assertRedirect(route('admin.ranks.index'))
            ->assertSessionHasErrors(['rank']);

        $this->assertDatabaseHas('ranks', ['id' => $rank->id]);
    }

    public function test_rank_management_requires_admin_authentication(): void
    {
        $this->get(route('admin.ranks.index'))->assertRedirect(route('login'));
        $this->delete(route('admin.ranks.destroy', Rank::firstOrFail()))->assertRedirect(route('login'));
    }

    private function member(string $id, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $id,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Rank Member ' . $id,
            'mobile_no' => '98765' . substr($id, -5),
            'pan_card_no' => 'RANK' . substr($id, -6),
            'email' => strtolower($id) . '@example.test',
            'status' => 'active',
        ]);
    }
}
