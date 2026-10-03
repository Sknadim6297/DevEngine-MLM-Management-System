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

class FinancialGenerationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $memberId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Test Member ' . $memberId,
            'mobile_no' => '9000000000',
            'pan_card_no' => 'PAN' . $memberId,
            'email' => strtolower($memberId) . '@example.test',
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $investmentId): Investment
    {
        return Investment::query()->create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '100.0000',
            'status' => 'expired',
            'closed_at' => now(),
        ]);
    }

    public function test_expired_investment_cannot_generate_roi(): void
    {
        $member = $this->member('ST100005');
        $investment = $this->investment($member, 'INV-LIFECYCLE-005');
        $before = RoiTransaction::query()->count();

        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-02', 'Asia/Kolkata'));

        $this->assertSame($before, RoiTransaction::query()->count());
        $this->assertDatabaseMissing('roi_transactions', ['investment_id' => $investment->investment_id]);
    }

    public function test_expired_investment_cannot_generate_level_commission(): void
    {
        $member = $this->member('ST100006');
        $investment = $this->investment($member, 'INV-LIFECYCLE-006');
        $before = LevelCommissionTransaction::query()->count();

        app(LevelCommissionGenerationService::class)->generateForInvestment(
            $investment,
            CarbonImmutable::parse('2026-09-02', 'Asia/Kolkata')
        );

        $this->assertSame($before, LevelCommissionTransaction::query()->count());
        $this->assertDatabaseMissing('level_commission_transactions', ['investment_id' => $investment->investment_id]);
    }

    public function test_roi_generation_after_expiry_creates_no_new_transactions(): void
    {
        $member = $this->member('ST100007');
        $investment = $this->investment($member, 'INV-LIFECYCLE-007');
        $before = RoiTransaction::query()->count();

        $this->artisan('roi:generate --date=2026-09-02')->assertExitCode(0);

        $this->assertSame($before, RoiTransaction::query()->count());
        $this->assertDatabaseMissing('roi_transactions', ['investment_id' => $investment->investment_id]);
    }

    public function test_commission_generation_after_expiry_creates_no_new_transactions(): void
    {
        $member = $this->member('ST100008');
        $investment = $this->investment($member, 'INV-LIFECYCLE-008');
        $before = LevelCommissionTransaction::query()->count();

        $this->artisan('commission:generate-level --date=2026-09-02')->assertExitCode(0);

        $this->assertSame($before, LevelCommissionTransaction::query()->count());
        $this->assertDatabaseMissing('level_commission_transactions', ['investment_id' => $investment->investment_id]);
    }
}