<?php

namespace Tests\Feature;

use App\Models\LevelCommissionTransaction;
use App\Models\Investment;
use App\Models\Member;
use App\Models\RoiTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_only_own_roi_and_total(): void
    {
        $member = $this->member('ST100001');
        $otherMember = $this->member('ST100002');
        $this->roi($member, 'ROI-OWN-1', 'INV-OWN', '4.0083', '2026-09-21');
        $this->roi($otherMember, 'ROI-OTHER-1', 'INV-OTHER', '99.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.roi', ['member_id' => $otherMember->member_id]))
            ->assertOk()
            ->assertSeeText('INV-OWN')
            ->assertSeeText('4.0083')
            ->assertSeeText('4.0083')
            ->assertDontSeeText('INV-OTHER')
            ->assertDontSeeText('99');
    }

    public function test_member_roi_date_filter_and_total_are_scoped_to_own_records(): void
    {
        $member = $this->member('ST100003');
        $otherMember = $this->member('ST100004');
        $this->roi($member, 'ROI-OWN-2', 'INV-OWN-2', '4.0000', '2026-09-19');
        $this->roi($member, 'ROI-OWN-3', 'INV-OWN-3', '8.0000', '2026-09-21');
        $this->roi($otherMember, 'ROI-OTHER-2', 'INV-OTHER-2', '100.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.roi', [
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-21',
        ]))->assertOk()
            ->assertSeeText('INV-OWN-3')
            ->assertDontSeeText('INV-OWN-2')
            ->assertDontSeeText('INV-OTHER-2')
            ->assertSeeText('8');
    }

    public function test_member_can_view_only_own_level_commission_with_level_and_date_filters(): void
    {
        $member = $this->member('ST100005');
        $otherMember = $this->member('ST100006');
        $this->commission($member, 'LC-OWN-1', 'INV-LC-1', 1, '3.0000', '2026-09-20');
        $this->commission($member, 'LC-OWN-2', 'INV-LC-2', 2, '7.0000', '2026-09-21');
        $this->commission($otherMember, 'LC-OTHER-1', 'INV-LC-3', 1, '100.0000', '2026-09-21');
        $this->memberSession($member);

        $this->get(route('member.reports.level-income', [
            'member_id' => $otherMember->member_id,
            'level' => 2,
            'from_date' => '2026-09-21',
            'to_date' => '2026-09-21',
        ]))->assertOk()
            ->assertSeeText('7');
    }

    public function test_member_report_routes_require_member_context(): void
    {
        $this->get(route('member.reports.roi'))->assertForbidden();
        $this->get(route('member.reports.level-income'))->assertForbidden();
    }

    private function member(string $memberId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member ' . $memberId,
            'mobile_no' => '98765' . substr($memberId, -5),
            'pan_card_no' => 'ABCDE' . substr($memberId, -5),
            'email' => strtolower($memberId) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function roi(Member $member, string $reference, string $investmentId, string $income, string $date): void
    {
        Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '2405.0000',
            'status' => 'active',
        ]);

        $transaction = RoiTransaction::create([
            'reference' => $reference,
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '2405.0000',
            'rate_percentage' => '5.000',
            'income_amount' => $income,
            'roi_date' => $date,
            'status' => 'generated',
            'withdrawable_on' => '2026-10-01',
        ]);

        $transaction->forceFill([
            'created_at' => CarbonImmutable::parse($date),
            'updated_at' => CarbonImmutable::parse($date),
        ])->save();
    }

    private function commission(Member $member, string $reference, string $investmentId, int $level, string $income, string $date): void
    {
        $transaction = LevelCommissionTransaction::create([
            'reference' => $reference,
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'from_member_id' => 'ST999999',
            'from_member_name' => 'Source Member',
            'level' => $level,
            'business_date' => $date,
            'on_amount' => '1000.0000',
            'rate_percentage' => '1.000',
            'income_amount' => $income,
        ]);

        $transaction->forceFill([
            'created_at' => CarbonImmutable::parse($date),
            'updated_at' => CarbonImmutable::parse($date),
        ])->save();
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }
}