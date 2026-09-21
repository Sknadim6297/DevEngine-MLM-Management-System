<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Services\LevelCommissionGenerationService;
use App\Services\RoiGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentComplete300CommandTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $memberId = 'ST100001'): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Test Member '.$memberId,
            'mobile_no' => '9000000000',
            'pan_card_no' => 'PAN'.$memberId,
            'email' => strtolower($memberId).'@example.test',
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

    private function combinedReturn(Investment $investment): string
    {
        $roi = (string) RoiTransaction::where('investment_id', $investment->investment_id)->sum('income_amount');
        $level = (string) LevelCommissionTransaction::where('investment_id', $investment->investment_id)->sum('income_amount');

        return bcadd($roi, $level, 4);
    }

    public function test_investment_below_300_percent_can_be_completed(): void
    {
        $member = $this->member();
        $investment = $this->investment($member, 'INVCOMP001', '100.0000');

        RoiTransaction::create([
            'reference' => 'ROI-INVCOMP001-20260901',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '40.0000',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        LevelCommissionTransaction::create([
            'reference' => 'LC-INVCOMP001-1',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST666666',
            'from_member_name' => 'Admin',
            'level' => 1,
            'business_date' => '2026-09-01',
            'on_amount' => '100.0000',
            'rate_percentage' => '1.000',
            'income_amount' => '80.0000',
        ]);

        $this->artisan('investment:complete-300', ['investment_id' => $investment->investment_id])
            ->assertExitCode(0);

        $investment->refresh();
        $this->assertSame('expired', $investment->status);
        $this->assertSame('300.0000', $this->combinedReturn($investment));
    }

    public function test_command_combines_roi_and_level_commission_before_cap(): void
    {
        $member = $this->member('ST100002');
        $investment = $this->investment($member, 'INVCOMP002', '200.0000');

        RoiTransaction::create([
            'reference' => 'ROI-INVCOMP002-20260901',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '200.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '75.0000',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        LevelCommissionTransaction::create([
            'reference' => 'LC-INVCOMP002-1',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST666666',
            'from_member_name' => 'Admin',
            'level' => 1,
            'business_date' => '2026-09-01',
            'on_amount' => '200.0000',
            'rate_percentage' => '1.000',
            'income_amount' => '100.0000',
        ]);

        $this->artisan('investment:complete-300', ['investment_id' => $investment->investment_id])
            ->assertExitCode(0);

        $this->assertSame('600.0000', $this->combinedReturn($investment->fresh()));
        $this->assertSame('expired', $investment->fresh()->status);
    }

    public function test_repeated_execution_does_not_exceed_300_percent(): void
    {
        $member = $this->member('ST100003');
        $investment = $this->investment($member, 'INVCOMP003', '100.0000');

        RoiTransaction::create([
            'reference' => 'ROI-INVCOMP003-20260901',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '50.0000',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        LevelCommissionTransaction::create([
            'reference' => 'LC-INVCOMP003-1',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST666666',
            'from_member_name' => 'Admin',
            'level' => 1,
            'business_date' => '2026-09-01',
            'on_amount' => '100.0000',
            'rate_percentage' => '1.000',
            'income_amount' => '40.0000',
        ]);

        $this->artisan('investment:complete-300', ['investment_id' => $investment->investment_id]);
        $firstTestRoiCount = RoiTransaction::where('investment_id', $investment->investment_id)->where('reference', 'like', 'TEST-%')->count();
        $firstTestLcCount = LevelCommissionTransaction::where('investment_id', $investment->investment_id)->where('reference', 'like', 'TEST-%')->count();

        $this->artisan('investment:complete-300', ['investment_id' => $investment->investment_id]);

        $this->assertSame('300.0000', $this->combinedReturn($investment->fresh()));
        $this->assertSame($firstTestRoiCount, RoiTransaction::where('investment_id', $investment->investment_id)->where('reference', 'like', 'TEST-%')->count());
        $this->assertSame($firstTestLcCount, LevelCommissionTransaction::where('investment_id', $investment->investment_id)->where('reference', 'like', 'TEST-%')->count());
        $this->assertSame('expired', $investment->fresh()->status);
    }

    public function test_existing_transaction_history_remains_intact(): void
    {
        $member = $this->member('ST100004');
        $investment = $this->investment($member, 'INVCOMP004', '150.0000');

        RoiTransaction::create([
            'reference' => 'ROI-REAL-INVCOMP004',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '150.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '15.0000',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        LevelCommissionTransaction::create([
            'reference' => 'LC-REAL-INVCOMP004',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST666666',
            'from_member_name' => 'Admin',
            'level' => 1,
            'business_date' => '2026-09-01',
            'on_amount' => '150.0000',
            'rate_percentage' => '1.000',
            'income_amount' => '45.0000',
        ]);

        $existingRoiCount = RoiTransaction::where('investment_id', $investment->investment_id)->count();
        $existingLevelCount = LevelCommissionTransaction::where('investment_id', $investment->investment_id)->count();

        $this->artisan('investment:complete-300', ['investment_id' => $investment->investment_id])
            ->assertExitCode(0);

        $this->assertSame($existingRoiCount + 1, RoiTransaction::where('investment_id', $investment->investment_id)->count());
        $this->assertSame($existingLevelCount + 1, LevelCommissionTransaction::where('investment_id', $investment->investment_id)->count());
        $this->assertDatabaseHas('roi_transactions', ['reference' => 'ROI-REAL-INVCOMP004']);
        $this->assertDatabaseHas('level_commission_transactions', ['reference' => 'LC-REAL-INVCOMP004']);
    }

    public function test_expired_investment_cannot_generate_roi(): void
    {
        $member = $this->member('ST100005');
        $investment = $this->investment($member, 'INVCOMP005', '100.0000');
        $investment->update(['status' => 'expired', 'closed_at' => now()]);

        $before = RoiTransaction::count();
        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-02', 'Asia/Kolkata'));

        $this->assertSame($before, RoiTransaction::count());
        $this->assertDatabaseMissing('roi_transactions', ['investment_id' => $investment->investment_id]);
    }

    public function test_expired_investment_cannot_generate_level_commission(): void
    {
        $member = $this->member('ST100006');
        $investment = $this->investment($member, 'INVCOMP006', '100.0000');
        $investment->update(['status' => 'expired', 'closed_at' => now()]);

        $before = LevelCommissionTransaction::count();
        app(LevelCommissionGenerationService::class)->generateForInvestment($investment, CarbonImmutable::parse('2026-09-02', 'Asia/Kolkata'));

        $this->assertSame($before, LevelCommissionTransaction::count());
        $this->assertDatabaseMissing('level_commission_transactions', ['investment_id' => $investment->investment_id]);
    }

    public function test_roi_generation_after_expiry_creates_zero_new_roi_transactions(): void
    {
        $member = $this->member('ST100007');
        $investment = $this->investment($member, 'INVCOMP007', '100.0000');
        $investment->update(['status' => 'expired', 'closed_at' => now()]);

        $before = RoiTransaction::count();
        $this->artisan('roi:generate --date=2026-09-02')->assertExitCode(0);

        $this->assertSame($before, RoiTransaction::count());
    }

    public function test_level_commission_generation_after_expiry_creates_zero_new_transactions(): void
    {
        $member = $this->member('ST100008');
        $investment = $this->investment($member, 'INVCOMP008', '100.0000');
        $investment->update(['status' => 'expired', 'closed_at' => now()]);

        $before = LevelCommissionTransaction::count();
        $this->artisan('commission:generate-level --date=2026-09-02')->assertExitCode(0);

        $this->assertSame($before, LevelCommissionTransaction::count());
    }
}
