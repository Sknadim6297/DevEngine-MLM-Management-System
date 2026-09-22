<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use App\Services\RankService;

/**
 * Credits upline members' Working Wallet with Level Commission for a qualifying investment.
 *
 * Base amount: the investment amount (on_amount), matching the existing
 * RoiTransaction pattern for the same investment_id relationship.
 * Wallet destination: working_wallet_amount, matching RoiGenerationService's
 * "workingIncome" naming and ActivationWalletController's existing
 * "Transfer from Working Wallet" logic.
 * Duplicate protection: unique(investment_id, member_id) on level_commission_transactions.
 */
class LevelCommissionGenerationService
{
    private const MONEY_SCALE = 4;

    private const MAX_LEVEL = 32;

    public function __construct(private readonly RankService $rankService)
    {
    }

    public function generateForInvestment(Investment $investment, ?CarbonImmutable $businessDate = null): array
    {
        if ($investment->status !== 'active') {
            return ['generated' => 0, 'skipped' => 0];
        }

        $businessDate ??= CarbonImmutable::now('Asia/Kolkata')->startOfDay();
        $result = ['generated' => 0, 'skipped' => 0];

        $sourceMember = Member::query()->where('member_id', $investment->member_id)->first();

        if (! $sourceMember) {
            return $result;
        }

        $currentSponsorId = $sourceMember->sponsor_id;
        $visited = [$sourceMember->member_id => true];

        $chain = [];
        for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
            if (empty($currentSponsorId) || isset($visited[$currentSponsorId])) {
                break;
            }

            $beneficiary = Member::query()
                ->where('member_id', $currentSponsorId)
                ->lockForUpdate()
                ->first();

            if (! $beneficiary) {
                break;
            }

            $visited[$beneficiary->member_id] = true;

            $chain[] = ['member' => $beneficiary, 'level' => $level];

            $currentSponsorId = $beneficiary->sponsor_id;
        }

        if ($chain === []) {
            return $result;
        }

        $rankResults = $this->rankService->calculateForMembers(
            array_map(fn (array $entry): string => $entry['member']->member_id, $chain)
        );

        foreach ($chain as $entry) {
            $rankData = $rankResults[$entry['member']->member_id];
            if ($entry['level'] > $rankData['unlocked_levels']) {
                $result['skipped']++;
                continue;
            }

            $outcome = $this->creditLevelCommission($investment, $entry['member'], $entry['level'], $businessDate);
            $result[$outcome]++;
        }

        return $result;
    }

    private function creditLevelCommission(Investment $investment, Member $beneficiary, int $level, CarbonImmutable $businessDate): string
    {
        if (LevelCommissionTransaction::query()
            ->where('investment_id', $investment->investment_id)
            ->where('member_id', $beneficiary->member_id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->exists()) {
            return 'skipped';
        }

        $rate = LevelCommissionRateResolver::forLevel($level);

        if (bccomp($rate, '0', 4) <= 0) {
            return 'skipped';
        }

        $onAmount = (string) $investment->amount;
        $incomeAmount = bcdiv(bcmul($onAmount, $rate, 8), '100', self::MONEY_SCALE);

        if (bccomp($incomeAmount, '0', self::MONEY_SCALE) <= 0) {
            return 'skipped';
        }

        try {
            LevelCommissionTransaction::create([
                'reference' => 'LC-' . $investment->investment_id . '-' . $beneficiary->member_id . '-' . $businessDate->format('Ymd'),
                'investment_id' => $investment->investment_id,
                'member_id' => $beneficiary->member_id,
                'member_name' => $beneficiary->member_name,
                'from_member_id' => $investment->member_id,
                'from_member_name' => $investment->member_name,
                'level' => $level,
                'business_date' => $businessDate->toDateString(),
                'on_amount' => $onAmount,
                'rate_percentage' => $rate,
                'income_amount' => $incomeAmount,
            ]);
        } catch (QueryException $exception) {
            return 'skipped';
        }

        $beneficiary->working_wallet_amount = bcadd((string) $beneficiary->working_wallet_amount, $incomeAmount, self::MONEY_SCALE);
        $beneficiary->save();

        return 'generated';
    }
}
