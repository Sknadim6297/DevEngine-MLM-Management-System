<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Services\RoiGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoiGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $memberId = 'ST100001'): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'ROI Member '.$memberId,
            'mobile_no' => substr('9'.$memberId.'0000000000', 0, 10),
            'pan_card_no' => 'PAN'.substr($memberId, -7),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $investmentId, string $amount, string $date): Investment
    {
        $investment = Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => 'active',
        ]);

        $investment->forceFill([
            'created_at' => CarbonImmutable::parse($date, RoiGenerationService::TIMEZONE)->setTimezone('UTC'),
            'updated_at' => CarbonImmutable::parse($date, RoiGenerationService::TIMEZONE)->setTimezone('UTC'),
        ])->save();

        return $investment;
    }

    public function test_investments_are_accepted_without_a_category(): void
    {
        $this->actingAs(User::factory()->create());
        $member = $this->member();

        foreach (['INVGROUP001', 'INVGROUP002'] as $investmentId) {
            $this->post(route('admin.investments.store'), [
            'investment_id' => $investmentId,
                'member_id' => $member->member_id,
                'amount' => 100,
            ])->assertRedirect(route('admin.investments.entry'));
        }

        $this->assertDatabaseCount('investments', 2);
    }

    public function test_roi_starts_the_day_after_investment_and_uses_five_percent_divided_by_thirty(): void
    {
        $member = $this->member();
        $this->investment($member, 'INVROI001', '2405.0000', '2026-09-16 09:00:00');
        $service = app(RoiGenerationService::class);

        $service->generateForDate(CarbonImmutable::parse('2026-09-16', RoiGenerationService::TIMEZONE));
        $this->assertDatabaseCount('roi_transactions', 0);

        $service->generateForDate(CarbonImmutable::parse('2026-09-17', RoiGenerationService::TIMEZONE));

        $this->assertDatabaseHas('roi_transactions', [
            'investment_id' => 'INVROI001',
            'member_id' => $member->member_id,
            'status' => 'generated',
        ]);
        $transaction = RoiTransaction::where('investment_id', 'INVROI001')->firstOrFail();
        $this->assertSame('2026-09-17', $transaction->roi_date->toDateString());
        $this->assertSame('4.0083', $transaction->income_amount);
        $this->assertSame('5.000', $transaction->rate_percentage);
        $this->assertSame('2026-10-01', $transaction->withdrawable_on->toDateString());
        $this->assertSame('4.0083', $member->fresh()->roi_wallet_amount);
    }

    public function test_multiple_investments_are_processed_and_capped_independently(): void
    {
        $member = $this->member();
        $this->investment($member, 'INVROI002', '100.0000', '2026-09-01');
        $this->investment($member, 'INVROI003', '500.0000', '2026-09-01');

        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-02', RoiGenerationService::TIMEZONE));

        $this->assertDatabaseHas('roi_transactions', ['investment_id' => 'INVROI002', 'income_amount' => '0.1666']);
        $this->assertDatabaseHas('roi_transactions', ['investment_id' => 'INVROI003', 'income_amount' => '0.8333']);
    }

    public function test_roi_does_not_depend_on_an_investment_category(): void
    {
        $member = $this->member();
        $investment = Investment::create([
            'investment_id' => 'INVHIST001',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '100.0000',
            'status' => 'active',
        ]);
        $investment->forceFill([
            'created_at' => CarbonImmutable::parse('2026-09-01', RoiGenerationService::TIMEZONE)->setTimezone('UTC'),
            'updated_at' => CarbonImmutable::parse('2026-09-01', RoiGenerationService::TIMEZONE)->setTimezone('UTC'),
        ])->save();

        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-02', RoiGenerationService::TIMEZONE));

        $this->assertDatabaseHas('roi_transactions', [
            'investment_id' => 'INVHIST001',
            'income_amount' => '0.1666',
        ]);
        $this->assertSame('0.1666', $member->fresh()->roi_wallet_amount);
    }

    public function test_partial_final_roi_obeys_combined_cap_and_expires_the_investment(): void
    {
        $member = $this->member();
        $investment = $this->investment($member, 'INVROI004', '100.0000', '2026-09-01');

        LevelCommissionTransaction::create([
            'reference' => 'LC-INVROI004',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST999999',
            'from_member_name' => 'Source',
            'level' => 1,
            'on_amount' => '100.0000',
            'rate_percentage' => '1.000',
            'income_amount' => '299.9000',
        ]);

        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-02', RoiGenerationService::TIMEZONE));

        $this->assertDatabaseHas('roi_transactions', ['investment_id' => 'INVROI004', 'income_amount' => '0.1000']);
        $this->assertDatabaseHas('investments', ['investment_id' => 'INVROI004', 'status' => 'expired']);

        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-03', RoiGenerationService::TIMEZONE));
        $this->assertDatabaseCount('roi_transactions', 1);
    }

    public function test_same_investment_and_date_cannot_receive_roi_twice(): void
    {
        $member = $this->member();
        $this->investment($member, 'INVROI005', '100.0000', '2026-09-01');
        $service = app(RoiGenerationService::class);
        $date = CarbonImmutable::parse('2026-09-02', RoiGenerationService::TIMEZONE);

        $service->generateForDate($date);
        $service->generateForDate($date);

        $this->assertDatabaseCount('roi_transactions', 1);
        $this->assertSame('0.1666', $member->fresh()->roi_wallet_amount);
    }

    public function test_transaction_failure_rolls_back_roi_wallet_and_investment_changes(): void
    {
        $member = $this->member();
        $this->investment($member, 'INVROI006', '100.0000', '2026-09-01');

        RoiTransaction::create([
            'reference' => 'ROI-INVROI006-20260902',
            'investment_id' => 'OTHERINV',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '0.1666',
            'roi_date' => '2026-09-01',
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        app(RoiGenerationService::class)->generateForDate(CarbonImmutable::parse('2026-09-02', RoiGenerationService::TIMEZONE));

        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        $this->assertDatabaseMissing('roi_transactions', ['investment_id' => 'INVROI006']);
        $this->assertDatabaseHas('investments', ['investment_id' => 'INVROI006', 'status' => 'active']);
    }

    public function test_command_uses_the_same_service_and_roi_report_reads_transactions(): void
    {
        $member = $this->member();
        $this->investment($member, 'INVROI007', '100.0000', '2026-01-31');

        $this->artisan('roi:generate --date=2026-02-01')->assertExitCode(0);
        $this->assertSame(
            '2026-03-01',
            RoiTransaction::where('investment_id', 'INVROI007')->firstOrFail()->withdrawable_on->toDateString()
        );

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.report.roi-report', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('INVROI007');
        $this->get(route('admin.report.roi-report'))->assertOk();

        auth()->logout();
        $this->get(route('admin.report.roi-report'))->assertRedirect(route('login'));
    }

    public function test_paginated_roi_and_level_reports_keep_full_filtered_totals_on_page_two(): void
    {
        $member = $this->member();

        foreach (range(1, 11) as $index) {
            RoiTransaction::create([
                'reference' => 'ROI-REPORT-PAGE-' . $index,
                'investment_id' => 'INV-REPORT-PAGE-' . $index,
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'on_amount' => '100.0000',
                'rate_percentage' => '5.000',
                'income_amount' => '1.0000',
                'roi_date' => '2026-09-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => 'generated',
                'withdrawable_on' => '2026-10-01',
            ]);

            LevelCommissionTransaction::create([
                'reference' => 'LC-REPORT-PAGE-' . $index,
                'investment_id' => 'INV-REPORT-PAGE-' . $index,
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'from_member_id' => 'ST999999',
                'from_member_name' => 'Source',
                'level' => 1,
                'on_amount' => '100.0000',
                'rate_percentage' => '1.000',
                'income_amount' => '2.0000',
            ]);
        }

        $this->actingAs(User::factory()->create());

        foreach ([1, 2] as $page) {
            $roiResponse = $this->get(route('admin.report.roi-report', [
                'member_id' => $member->member_id,
                'page' => $page,
            ]));

            $roiResponse->assertOk()
                ->assertSee('<strong>11</strong>', false)
                ->assertDontSee('&laquo;', false)
                ->assertDontSee('&raquo;', false)
                ->assertDontSee('«', false)
                ->assertDontSee('»', false)
                ->assertSee('>Previous', false)
                ->assertSee('>Next', false);
            $this->assertSame(1, substr_count($roiResponse->getContent(), 'Showing'));
            $this->assertSame(1, substr_count($roiResponse->getContent(), '>Previous'));
            $this->assertSame(1, substr_count($roiResponse->getContent(), '>Next'));

            if ($page === 1) {
                $this->assertStringContainsString('page-item disabled', $roiResponse->getContent());
                $this->assertStringContainsString('page=2', $roiResponse->getContent());
            } else {
                $this->assertStringContainsString('rel="prev"', $roiResponse->getContent());
                $this->assertStringContainsString('page=1', $roiResponse->getContent());
                $this->assertStringContainsString('page-item disabled', $roiResponse->getContent());
            }

            $levelResponse = $this->get(route('admin.report.level-income', [
                'member_id' => $member->member_id,
                'per_page' => 10,
                'page' => $page,
            ]));

            $levelResponse->assertOk()
                ->assertSee('<strong>22</strong>', false);
            $this->assertSame(1, substr_count($levelResponse->getContent(), 'Showing'));
        }
    }
}
