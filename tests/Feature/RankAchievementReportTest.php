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

    public function test_rank_filter_returns_only_selected_rank_and_works_with_date_range_for_admin(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $silverMember = $this->member('RANKF003', 'ST666666');
        $goldMember = $this->member('RANKF004', 'ST666666');
        $this->rankAchievementFor($silverMember, 'Silver', '4500.0000', '2026-09-10');
        $this->rankAchievementFor($goldMember, 'Gold', '12000.0000', '2026-09-12');

        $silverRankId = Rank::where('name', 'Silver')->value('id');
        $goldRankId = Rank::where('name', 'Gold')->value('id');

        $this->get(route('admin.report.rank-achievement', [
            'rank_id' => $silverRankId,
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
        ]))->assertOk()->assertSeeText($silverMember->member_id)->assertDontSeeText($goldMember->member_id);

        $this->get(route('admin.report.rank-achievement', ['rank_id' => $goldRankId]))
            ->assertOk()
            ->assertSeeText($goldMember->member_id)
            ->assertDontSeeText($silverMember->member_id);
    }

    public function test_member_rank_filter_is_scoped_to_member_ownership_and_preserves_pagination_state(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = $this->member('RANKF005', 'ST666666');
        $other = $this->member('RANKF006', 'ST666666');
        session(['member_context_id' => $member->member_id]);

        $this->rankAchievementFor($member, 'Silver', '5000.0000', '2026-09-01');
        $this->rankAchievementFor($member, 'Gold', '12000.0000', '2026-09-15');
        $this->rankAchievementFor($other, 'Gold', '25000.0000', '2026-09-20');

        $silverRankId = Rank::where('name', 'Silver')->value('id');
        $goldRankId = Rank::where('name', 'Gold')->value('id');

        $this->get(route('member.reports.rank-achievement', ['rank_id' => $silverRankId]))
            ->assertOk()
            ->assertSeeText($member->member_id)
            ->assertDontSeeText($other->member_id);

        $this->get(route('member.reports.rank-achievement', [
            'rank_id' => $goldRankId,
            'from_date' => '2026-09-10',
            'to_date' => '2026-09-30',
        ]))->assertOk()->assertSeeText($member->member_id)->assertDontSeeText($other->member_id);
    }

    public function test_unauthenticated_users_cannot_access_rank_achievement_report(): void
    {
        $this->get(route('admin.report.rank-achievement'))->assertRedirect(route('login'));
    }

    public function test_rank_progression_test_dataset_resets_and_creates_exactly_ten_achievements(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Member::query()->create([
            'member_id' => 'LEGACY-ALPHA',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Legacy Alpha',
            'mobile_no' => '9876500001',
            'pan_card_no' => 'LEGACY1',
            'email' => 'legacy.alpha@example.test',
            'status' => 'active',
            'rank_id' => Rank::where('name', 'Blue Diamond')->value('id'),
        ]);

        RankAchievement::query()->create([
            'member_id' => 'LEGACY-ALPHA',
            'member_name' => 'Legacy Alpha',
            'rank_id' => Rank::where('name', 'Diamond')->value('id'),
            'qualifying_business_amount' => '250000.0000',
            'achieved_at' => now('Asia/Kolkata')->subDay(),
        ]);

        $this->artisan('rank:test-dataset')->assertExitCode(0);

        $this->assertSame(10, RankAchievement::query()->count());
        $this->assertSame(0, Member::query()->whereNotNull('rank_id')->where('member_id', 'not like', 'DEMO-%')->count());
        $this->assertSame('Master Blaster', Member::where('member_id', 'DEMO-101')->first()->rank?->name);

        $this->get(route('admin.report.rank-achievement'))
            ->assertOk()
            ->assertSeeText('DEMO-101')
            ->assertSeeText('DEMO-104')
            ->assertDontSeeText('LEGACY-ALPHA');

        $this->assertDatabaseHas('rank_achievements', ['member_id' => 'DEMO-101', 'rank_id' => Rank::where('name', 'Diamond')->value('id')]);
        $this->assertDatabaseHas('rank_achievements', ['member_id' => 'DEMO-101', 'rank_id' => Rank::where('name', 'Blue Diamond')->value('id')]);
        $this->assertDatabaseHas('rank_achievements', ['member_id' => 'DEMO-101', 'rank_id' => Rank::where('name', 'Master Blaster')->value('id')]);
    }

    public function test_rank_advance_only_awards_the_immediate_next_rank_for_demo_members(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);

        $member = Member::query()->firstOrCreate(
            ['member_id' => 'DEMO-101'],
            [
                'sponsor_id' => 'ST666666',
                'sponsor_name' => 'Admin',
                'member_name' => 'Demo Test Leader',
                'mobile_no' => '9876520001',
                'pan_card_no' => 'ADV0001',
                'email' => 'demo101@example.test',
                'status' => 'active',
                'rank_id' => Rank::where('name', 'Diamond')->value('id'),
            ]
        );

        RankAchievement::query()->where('member_id', $member->member_id)->delete();

        $child = Member::create([
            'member_id' => 'DEMO-ADVANCE-CHILD',
            'sponsor_id' => $member->member_id,
            'sponsor_name' => $member->member_name,
            'member_name' => 'Demo Advance Child',
            'mobile_no' => '9876520002',
            'pan_card_no' => 'ADV0002',
            'email' => 'demo.advance.child@example.test',
            'status' => 'active',
        ]);

        Investment::create([
            'investment_id' => 'ADV-QUALIFY-001',
            'member_id' => $child->member_id,
            'member_name' => $child->member_name,
            'amount' => '600000.0000',
            'status' => 'active',
        ]);

        $this->assertSame('Diamond', $member->fresh()->rank->name);
        $this->artisan('rank:advance', ['--member-id' => $member->member_id])->assertExitCode(0);

        $member->refresh();
        $this->assertSame('Blue Diamond', $member->rank->name);
        $this->assertSame(1, RankAchievement::query()->where('member_id', $member->member_id)->where('rank_id', Rank::where('name', 'Blue Diamond')->value('id'))->count());
        $this->assertSame(0, RankAchievement::query()->where('member_id', $member->member_id)->where('rank_id', Rank::where('name', 'Master Blaster')->value('id'))->count());
    }

    public function test_rank_advance_reconciles_downgrade_when_business_is_lost_and_keeps_history(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);

        $member = $this->member('RANKG001', 'ST666666');
        $member->forceFill(['rank_id' => Rank::where('name', 'Gold')->value('id')])->saveQuietly();

        $left = $this->member('RANKG002', $member->member_id);
        $right = $this->member('RANKG003', $member->member_id);

        Investment::query()->create([
            'investment_id' => 'INV-RANKG-01',
            'member_id' => $left->member_id,
            'member_name' => $left->member_name,
            'amount' => '8000.0000',
            'status' => 'active',
        ]);
        Investment::query()->create([
            'investment_id' => 'INV-RANKG-02',
            'member_id' => $right->member_id,
            'member_name' => $right->member_name,
            'amount' => '6000.0000',
            'status' => 'active',
        ]);

        $goldRankId = Rank::where('name', 'Gold')->value('id');
        RankAchievement::query()->create([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'rank_id' => $goldRankId,
            'qualifying_business_amount' => '14000.0000',
            'achieved_at' => now('Asia/Kolkata')->subDay(),
        ]);

        $left->refresh();
        $left->update(['status' => 'inactive']);
        $left->investments()->update(['status' => 'expired']);

        $business = app(RankService::class)->calculateForMember($member);
        $this->assertSame('6000.0000', $business['full_team_business']);
        $this->assertSame('Silver', $business['current_rank']->name);

        $this->artisan('rank:advance', ['--member-id' => $member->member_id])->assertExitCode(0);

        $member->refresh();
        $this->assertSame('Silver', $member->rank->name);
        $this->assertDatabaseHas('rank_achievements', [
            'member_id' => $member->member_id,
            'rank_id' => $goldRankId,
        ]);
        $this->assertSame(1, RankAchievement::query()->where('member_id', $member->member_id)->where('rank_id', $goldRankId)->count());
    }

    public function test_rank_advance_promotes_one_rank_only_when_next_threshold_is_met(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);

        $member = $this->member('RANKH001', 'ST666666');
        $member->forceFill(['rank_id' => Rank::where('name', 'Silver')->value('id')])->saveQuietly();

        $left = $this->member('RANKH002', $member->member_id);
        $right = $this->member('RANKH003', $member->member_id);

        Investment::query()->create([
            'investment_id' => 'INV-RANKH-01',
            'member_id' => $left->member_id,
            'member_name' => $left->member_name,
            'amount' => '15000.0000',
            'status' => 'active',
        ]);
        Investment::query()->create([
            'investment_id' => 'INV-RANKH-02',
            'member_id' => $right->member_id,
            'member_name' => $right->member_name,
            'amount' => '15000.0000',
            'status' => 'active',
        ]);

        $this->artisan('rank:advance', ['--member-id' => $member->member_id])->assertExitCode(0);

        $member->refresh();
        $this->assertSame('Gold', $member->rank->name);
        $this->assertSame(1, RankAchievement::query()->where('member_id', $member->member_id)->where('rank_id', Rank::where('name', 'Gold')->value('id'))->count());
        $this->assertSame(0, RankAchievement::query()->where('member_id', $member->member_id)->where('rank_id', Rank::where('name', 'Platinum')->value('id'))->count());
    }

    public function test_roi_generation_marks_investment_expired_and_excludes_it_from_business(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);

        $member = $this->member('RANKI001', 'ST666666');
        $createdAt = CarbonImmutable::now('Asia/Kolkata')->subDays(5000)->startOfDay();

        $investment = Investment::query()->create([
            'investment_id' => 'INV-RANKI-01',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '100.0000',
            'status' => 'active',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $service = app(\App\Services\RoiGenerationService::class);
        $date = $createdAt->addDay();

        for ($day = 0; $day < 5000; $day++) {
            $service->generateForDate($date->addDays($day));
            if ($investment->fresh()->status === 'expired') {
                break;
            }
        }

        $investment->refresh();
        $this->assertSame('expired', $investment->status);
        $this->assertSame('0.0000', app(RankService::class)->calculateForMember($member)['full_team_business']);
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
