<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\LevelCommission;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use App\Services\LevelCommissionGenerationService;
use App\Services\RoiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkingWalletCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $id, ?string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $id,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member ' . $id,
            'mobile_no' => '9' . substr(str_pad($id, 9, '0'), -9),
            'pan_card_no' => 'ABCDE1234' . substr($id, -1),
            'email' => strtolower($id) . '@example.com',
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $investmentId, string $amount): Investment
    {
        return Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => 'active',
        ]);
    }

    public function test_configured_rates_match_documented_levels(): void
    {
        $this->assertSame('1.0000', (string) LevelCommission::where('level', 1)->value('percentage'));
        $this->assertSame('0.5000', (string) LevelCommission::where('level', 2)->value('percentage'));
        $this->assertSame('0.5000', (string) LevelCommission::where('level', 3)->value('percentage'));
        $this->assertSame('0.3000', (string) LevelCommission::where('level', 4)->value('percentage'));
        $this->assertSame('0.2500', (string) LevelCommission::where('level', 20)->value('percentage'));
        $this->assertSame('0.2000', (string) LevelCommission::where('level', 21)->value('percentage'));
        $this->assertSame('0.2000', (string) LevelCommission::where('level', 32)->value('percentage'));
    }

    public function test_investment_credits_upline_working_wallet_by_genealogy_level(): void
    {
        // ST666666 root -> A -> B -> C (three-level real sponsor chain)
        $memberA = $this->member('MB100001', 'ST666666');
        $memberB = $this->member('MB100002', $memberA->member_id);
        $memberC = $this->member('MB100003', $memberB->member_id);

        $investment = Investment::create([
            'investment_id' => 'INVWORK001',
            'member_id' => $memberC->member_id,
            'member_name' => $memberC->member_name,
            'amount' => 1000,
            'status' => 'active',
        ]);
        $this->investment($memberC, 'INVQUAL001', '6000.0000');

        $generationId = (int) DB::table('report_summary_generations')->insertGetId([
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('report_summary_state')->where('id', 1)->update([
            'active_generation_id' => $generationId,
            'writes_enabled' => true,
        ]);

        app(LevelCommissionGenerationService::class)->generateForInvestment($investment);

        $memberA->refresh();
        $memberB->refresh();

        // Level 1 (direct sponsor B) = 1% of 1000 = 10.00
        $this->assertSame('10.0000', (string) $memberB->working_wallet_amount);
        // Level 2 (sponsor of B) = 0.5% of 1000 = 5.00
        $this->assertSame('5.0000', (string) $memberA->working_wallet_amount);

        $this->assertDatabaseHas('level_commission_transactions', [
            'investment_id' => 'INVWORK001',
            'member_id' => $memberB->member_id,
            'level' => 1,
            'income_amount' => 10.0000,
        ]);
        $this->assertDatabaseHas('level_commission_transactions', [
            'investment_id' => 'INVWORK001',
            'member_id' => $memberA->member_id,
            'level' => 2,
            'income_amount' => 5.0000,
        ]);

        $summary = DB::table('level_commission_report_global_daily_summaries')
            ->where('generation_id', $generationId)
            ->first();
        $this->assertNotNull($summary);
        $this->assertSame('2', (string) $summary->transaction_count);
        $this->assertSame('15.0000', number_format((float) $summary->total_income_amount, 4, '.', ''));
    }

    public function test_duplicate_generation_does_not_double_credit_working_wallet(): void
    {
        $memberA = $this->member('MB200001', 'ST666666');
        $memberB = $this->member('MB200002', $memberA->member_id);

        $investment = Investment::create([
            'investment_id' => 'INVWORK002',
            'member_id' => $memberB->member_id,
            'member_name' => $memberB->member_name,
            'amount' => 500,
            'status' => 'active',
        ]);
        $this->investment($memberB, 'INVQUAL002', '6000.0000');

        $generationId = (int) DB::table('report_summary_generations')->insertGetId([
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('report_summary_state')->where('id', 1)->update([
            'active_generation_id' => $generationId,
            'writes_enabled' => true,
        ]);

        $service = app(LevelCommissionGenerationService::class);
        $service->generateForInvestment($investment);
        $service->generateForInvestment($investment);

        $memberA->refresh();

        $this->assertSame('5.0000', (string) $memberA->working_wallet_amount);
        $this->assertSame(1, LevelCommissionTransaction::where('investment_id', 'INVWORK002')->count());
        $this->assertSame(1, (int) DB::table('level_commission_report_global_daily_summaries')
            ->where('generation_id', $generationId)
            ->sum('transaction_count'));
    }

    public function test_level_commission_uses_only_the_remaining_combined_income_cap(): void
    {
        $memberA = $this->member('MB210001', 'ST666666');
        $memberB = $this->member('MB210002', $memberA->member_id);
        $investment = $this->investment($memberB, 'INVCAP001', '100.0000');
        $this->investment($memberB, 'INVQUALCAP', '6000.0000');

        RoiTransaction::query()->create([
            'reference' => 'ROI-INVCAP001-20260901',
            'investment_id' => $investment->investment_id,
            'member_id' => $memberB->member_id,
            'member_name' => $memberB->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '299.5000',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        app(LevelCommissionGenerationService::class)->generateForInvestment(
            $investment,
            CarbonImmutable::parse('2026-09-02', 'Asia/Kolkata')
        );

        $commission = LevelCommissionTransaction::query()
            ->where('investment_id', $investment->investment_id)
            ->firstOrFail();
        $this->assertSame('0.5000', (string) $commission->income_amount);
        $this->assertSame('0.5000', (string) $memberA->fresh()->working_wallet_amount);
        $this->assertSame('expired', $investment->fresh()->status);
        $this->assertSame('300.0000', bcadd(
            (string) RoiTransaction::query()->where('investment_id', $investment->investment_id)->sum('income_amount'),
            (string) LevelCommissionTransaction::query()->where('investment_id', $investment->investment_id)->sum('income_amount'),
            4
        ));
    }

    public function test_commission_cannot_credit_after_roi_consumes_the_shared_remaining_cap(): void
    {
        $upline = $this->member('MB220001', 'ST666666');
        $source = $this->member('MB220002', $upline->member_id);
        $this->investment($source, 'INVQUAL2200', '6000.0000');
        $investment = $this->investment($source, 'INVCAP2200', '100.0000');
        $createdAt = CarbonImmutable::parse('2021-01-01', 'Asia/Kolkata')->setTimezone('UTC');
        $investment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        $source->roi_wallet_amount = '299.9000';
        $source->save();

        RoiTransaction::query()->create([
            'reference' => 'ROI-INVCAP2200-HISTORY',
            'investment_id' => $investment->investment_id,
            'member_id' => $source->member_id,
            'member_name' => $source->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '299.9000',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        $businessDate = CarbonImmutable::parse('2026-09-02', 'Asia/Kolkata');
        app(RoiGenerationService::class)->generateForDate($businessDate, 'INVCAP2200');
        $commissionResult = app(LevelCommissionGenerationService::class)
            ->generateForInvestment($investment->fresh(), $businessDate);

        $this->assertSame(0, $commissionResult['generated']);
        $this->assertSame('expired', $investment->fresh()->status);
        $this->assertSame('300.0000', bcadd((string) RoiTransaction::query()
            ->where('investment_id', $investment->investment_id)
            ->sum('income_amount'), '0', 4));
        $this->assertSame('0.0000', bcadd((string) LevelCommissionTransaction::query()
            ->where('investment_id', $investment->investment_id)
            ->sum('income_amount'), '0', 4));
        $this->assertSame('300.0000', bcadd((string) $source->fresh()->roi_wallet_amount, '0', 4));
        $this->assertSame('0.0000', bcadd((string) $upline->fresh()->working_wallet_amount, '0', 4));
    }

    public function test_future_commission_command_is_rejected_in_production_environment(): void
    {
        $this->app->instance('env', 'local');
        config(['financial.accelerated.enabled' => false]);

        $this->artisan('commission:generate-level --date=2040-01-01')->assertExitCode(1);

        $this->assertDatabaseCount('level_commission_transactions', 0);
    }

    public function test_missing_sponsor_stops_the_chain_without_error(): void
    {
        $member = $this->member('MB300001', 'ST666666');

        $investment = Investment::create([
            'investment_id' => 'INVWORK003',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => 300,
            'status' => 'active',
        ]);

        $result = app(LevelCommissionGenerationService::class)->generateForInvestment($investment);

        $this->assertSame(0, $result['generated']);
        $this->assertDatabaseCount('level_commission_transactions', 0);
    }

    public function test_investment_store_route_credits_sponsor_working_wallet(): void
    {
        $this->actingAs(User::factory()->create());

        $memberA = $this->member('MB400001', 'ST666666');
        $memberB = $this->member('MB400002', $memberA->member_id);
        $this->investment($memberB, 'INVQUAL004', '6000.0000');

        $this->post(route('admin.investments.store'), [
            'investment_id' => 'INVWORK004',
            'member_id' => $memberB->member_id,
            'amount' => 1000,
        ])->assertRedirect(route('admin.investments.entry'));

        $memberA->refresh();

        $this->assertSame('10.0000', (string) $memberA->working_wallet_amount);
    }

    public function test_daily_generation_uses_business_date_and_report_shows_each_period(): void
    {
        $memberA = $this->member('MB500001', 'ST666666');
        $memberB = $this->member('MB500002', $memberA->member_id);
        $memberC = $this->member('MB500003', $memberB->member_id);

        Investment::create([
            'investment_id' => 'INVWORK005',
            'member_id' => $memberC->member_id,
            'member_name' => $memberC->member_name,
            'amount' => '1000.0000',
            'status' => 'active',
        ]);
        $this->investment($memberC, 'INVQUAL005', '6000.0000');

        $service = app(LevelCommissionGenerationService::class);
        $firstDate = CarbonImmutable::parse('2026-09-17', 'Asia/Kolkata');
        $secondDate = CarbonImmutable::parse('2026-09-18', 'Asia/Kolkata');

        $service->generateForInvestment(Investment::where('investment_id', 'INVWORK005')->firstOrFail(), $firstDate);
        $service->generateForInvestment(Investment::where('investment_id', 'INVWORK005')->firstOrFail(), $firstDate);
        $service->generateForInvestment(Investment::where('investment_id', 'INVWORK005')->firstOrFail(), $secondDate);

        $this->assertSame(4, LevelCommissionTransaction::where('investment_id', 'INVWORK005')->count());
        $this->assertSame('20.0000', (string) $memberB->fresh()->working_wallet_amount);
        $this->assertSame('10.0000', (string) $memberA->fresh()->working_wallet_amount);
        $nextDayCommission = LevelCommissionTransaction::where('investment_id', 'INVWORK005')
            ->where('member_id', $memberB->member_id)
            ->where('level', 1)
            ->whereDate('business_date', '2026-09-18')
            ->firstOrFail();
        $this->assertSame('1000.0000', (string) $nextDayCommission->on_amount);
        $this->assertSame('10.0000', (string) $nextDayCommission->income_amount);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.report.level-income', ['member_id' => $memberB->member_id]))
            ->assertOk()
            ->assertSeeText('10');
    }
}
