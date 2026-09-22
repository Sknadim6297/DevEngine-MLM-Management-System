<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use App\Models\User;
use App\Services\RankService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankAchievementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_rank_achievement_is_recorded_when_a_member_newly_reaches_a_rank(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $root = $this->member('RANKA001', 'ST666666');
        $child = $this->member('RANKA002', $root->member_id);

        Investment::create([
            'investment_id' => 'INV-RANKA-01',
            'member_id' => $child->member_id,
            'member_name' => $child->member_name,
            'amount' => '7000.0000',
            'status' => 'active',
        ]);

        app(RankService::class)->syncMemberRankAchievement($root);

        $this->assertDatabaseHas('rank_achievements', [
            'member_id' => $root->member_id,
            'rank_id' => Rank::where('name', 'Silver')->value('id'),
        ]);
    }

    public function test_same_rank_is_not_recorded_twice(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $root = $this->member('RANKB001', 'ST666666');
        $child = $this->member('RANKB002', $root->member_id);

        Investment::create([
            'investment_id' => 'INV-RANKB-01',
            'member_id' => $child->member_id,
            'member_name' => $child->member_name,
            'amount' => '6000.0000',
            'status' => 'active',
        ]);

        $service = app(RankService::class);
        $service->syncMemberRankAchievement($root);
        $service->syncMemberRankAchievement($root);

        $this->assertSame(1, RankAchievement::query()->where('member_id', $root->member_id)->count());
    }

    public function test_higher_rank_achievement_preserves_previous_rank_history(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $root = $this->member('RANKC001', 'ST666666');
        $left = $this->member('RANKC002', $root->member_id);
        $right = $this->member('RANKC003', $root->member_id);

        Investment::create([
            'investment_id' => 'INV-RANKC-01',
            'member_id' => $left->member_id,
            'member_name' => $left->member_name,
            'amount' => '6000.0000',
            'status' => 'active',
        ]);
        Investment::create([
            'investment_id' => 'INV-RANKC-02',
            'member_id' => $right->member_id,
            'member_name' => $right->member_name,
            'amount' => '6000.0000',
            'status' => 'active',
        ]);

        $service = app(RankService::class);
        $service->syncMemberRankAchievement($root);

        Investment::create([
            'investment_id' => 'INV-RANKC-03',
            'member_id' => $left->member_id,
            'member_name' => $left->member_name,
            'amount' => '4000.0000',
            'status' => 'active',
        ]);

        $service->syncMemberRankAchievement($root);

        $history = RankAchievement::query()->where('member_id', $root->member_id)->orderBy('rank_id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('Silver', $history->first()->rank->name);
        $this->assertSame('Gold', $history->last()->rank->name);
    }

    public function test_member_id_filter_works_on_rank_achievement_report(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $first = $this->member('RANKD001', 'ST666666');
        $second = $this->member('RANKD002', 'ST666666');
        $this->rankAchievementFor($first, 'Silver', '1000.0000');
        $this->rankAchievementFor($second, 'Silver', '800.0000');

        $this->get(route('admin.report.rank-achievement', ['member_id' => $first->member_id]))
            ->assertOk()
            ->assertSeeText($first->member_id)
            ->assertDontSeeText($second->member_id);
    }

    public function test_date_filters_work_on_rank_achievement_report(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $first = $this->member('RANKE001', 'ST666666');
        $this->rankAchievementFor($first, 'Silver', '1200.0000', '2026-09-10');

        $this->get(route('admin.report.rank-achievement', [
            'from_date' => '2026-09-11',
            'to_date' => '2026-09-30',
        ]))->assertOk()->assertDontSeeText($first->member_id);

        $this->get(route('admin.report.rank-achievement', [
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-20',
        ]))->assertOk()->assertSeeText($first->member_id);
    }

    public function test_total_amount_is_correct_on_rank_achievement_report(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $first = $this->member('RANKF001', 'ST666666');
        $second = $this->member('RANKF002', 'ST666666');
        $this->rankAchievementFor($first, 'Silver', '6000.0000');
        $this->rankAchievementFor($second, 'Gold', '9000.0000');

        $this->get(route('admin.report.rank-achievement'))
            ->assertOk()
            ->assertSeeText('15000');
    }

    public function test_unauthenticated_users_cannot_access_rank_achievement_report(): void
    {
        $this->get(route('admin.report.rank-achievement'))->assertRedirect(route('login'));
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

    private function rankAchievementFor(Member $member, string $rankName, string $businessAmount, ?string $achievedAt = null): void
    {
        $rank = Rank::where('name', $rankName)->firstOrFail();

        RankAchievement::query()->updateOrCreate(
            ['member_id' => $member->member_id, 'rank_id' => $rank->id],
            [
                'member_name' => $member->member_name,
                'achieved_at' => $achievedAt ? CarbonImmutable::parse($achievedAt) : CarbonImmutable::now('Asia/Kolkata'),
                'qualifying_business_amount' => $businessAmount,
            ]
        );
    }
}
