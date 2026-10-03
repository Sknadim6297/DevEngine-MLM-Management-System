<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankAdvanceBusinessDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_rank_advancement_uses_business_date_and_is_idempotent_for_that_date(): void
    {
        $this->seed(\Database\Seeders\RankSeeder::class);
        $member = Member::create([
            'member_id' => 'RANK-DATE-001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Rank Date Member',
            'mobile_no' => '9000000001',
            'pan_card_no' => 'RANKD1234A',
            'email' => 'rank-date@example.test',
            'status' => 'active',
        ]);
        $child = Member::create([
            'member_id' => 'RANK-DATE-002',
            'sponsor_id' => $member->member_id,
            'sponsor_name' => $member->member_name,
            'member_name' => 'Rank Date Child',
            'mobile_no' => '9000000002',
            'pan_card_no' => 'RANKD1234B',
            'email' => 'rank-date-child@example.test',
            'status' => 'active',
        ]);
        $investment = Investment::create([
            'investment_id' => 'INV-RANK-DATE-001',
            'member_id' => $child->member_id,
            'member_name' => $child->member_name,
            'amount' => '24000.0000',
            'status' => 'active',
        ]);
        $investment->forceFill([
            'created_at' => CarbonImmutable::parse('2026-10-01', 'Asia/Kolkata')->setTimezone('UTC'),
            'updated_at' => CarbonImmutable::parse('2026-10-01', 'Asia/Kolkata')->setTimezone('UTC'),
        ])->save();

        $silver = Rank::query()->where('name', 'Silver')->firstOrFail();
        $gold = Rank::query()->where('name', 'Gold')->firstOrFail();

        $this->artisan('rank:advance', ['--date' => '2026-10-02'])->assertExitCode(0);
        $this->assertSame($silver->id, (int) $member->fresh()->rank_id);
        $achievement = RankAchievement::query()->where('member_id', $member->member_id)->firstOrFail();
        $this->assertSame('2026-10-02', $achievement->achieved_at->toDateString());

        $this->artisan('rank:advance', ['--date' => '2026-10-02'])->assertExitCode(0);
        $this->assertSame($silver->id, (int) $member->fresh()->rank_id);
        $this->assertSame(1, RankAchievement::query()->where('member_id', $member->member_id)->count());

        $this->artisan('rank:advance', ['--date' => '2026-10-03'])->assertExitCode(0);
        $this->assertSame($gold->id, (int) $member->fresh()->rank_id);
        $this->assertDatabaseHas('rank_achievements', [
            'member_id' => $member->member_id,
            'rank_id' => $gold->id,
            'achieved_at' => '2026-10-03 23:59:59',
        ]);
    }
}
