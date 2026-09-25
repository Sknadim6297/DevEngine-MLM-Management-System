<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    private ?Collection $membersById = null;

    private array $ratesByLevel = [];

    private array $rankResultsByMember = [];

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

        $membersById = $this->membersById();
        $sourceMember = $membersById->get($investment->member_id);

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

            $beneficiary = $membersById->get($currentSponsorId);

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

        $chainMemberIds = array_map(fn (array $entry): string => $entry['member']->member_id, $chain);
        $uncachedMemberIds = array_values(array_diff($chainMemberIds, array_keys($this->rankResultsByMember)));
        if ($uncachedMemberIds !== []) {
            $this->rankResultsByMember = array_replace(
                $this->rankResultsByMember,
                $this->rankService->calculateForMembers($uncachedMemberIds)
            );
        }

        $eligibleEntries = [];
        foreach ($chain as $entry) {
            $rankData = $this->rankResultsByMember[$entry['member']->member_id];
            if ($entry['level'] > $rankData['unlocked_levels']) {
                $result['skipped']++;
                continue;
            }

            $rate = $this->rateForLevel($entry['level']);
            if (bccomp($rate, '0', 4) <= 0) {
                $result['skipped']++;
                continue;
            }

            $onAmount = (string) $investment->amount;
            $incomeAmount = bcdiv(bcmul($onAmount, $rate, 8), '100', self::MONEY_SCALE);
            if (bccomp($incomeAmount, '0', self::MONEY_SCALE) <= 0) {
                $result['skipped']++;
                continue;
            }

            $eligibleEntries[] = [
                'member' => $entry['member'],
                'level' => $entry['level'],
                'rate' => $rate,
                'income_amount' => $incomeAmount,
            ];
        }

        if ($eligibleEntries === []) {
            return $result;
        }

        $memberIds = array_map(fn (array $entry): string => $entry['member']->member_id, $eligibleEntries);
        $existingMemberIds = LevelCommissionTransaction::query()
            ->where('investment_id', $investment->investment_id)
            ->whereIn('member_id', $memberIds)
            ->whereDate('business_date', $businessDate->toDateString())
            ->pluck('member_id')
            ->all();
        $existingMemberIds = array_fill_keys($existingMemberIds, true);
        $newEntries = array_values(array_filter(
            $eligibleEntries,
            fn (array $entry): bool => ! isset($existingMemberIds[$entry['member']->member_id])
        ));
        $result['skipped'] += count($eligibleEntries) - count($newEntries);

        if ($newEntries === []) {
            return $result;
        }

        $rows = array_map(function (array $entry) use ($investment, $businessDate): array {
            $member = $entry['member'];
            return [
                'reference' => 'LC-' . $investment->investment_id . '-' . $member->member_id . '-' . $businessDate->format('Ymd'),
                'investment_id' => $investment->investment_id,
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'from_member_id' => $investment->member_id,
                'from_member_name' => $investment->member_name,
                'level' => $entry['level'],
                'business_date' => $businessDate->toDateString(),
                'on_amount' => $investment->amount,
                'rate_percentage' => $entry['rate'],
                'income_amount' => $entry['income_amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $newEntries);

        $successfulEntries = $newEntries;
        try {
            LevelCommissionTransaction::query()->insert($rows);
        } catch (QueryException $exception) {
            $successfulEntries = [];
            foreach ($newEntries as $index => $entry) {
                try {
                    LevelCommissionTransaction::query()->insert([$rows[$index]]);
                    $successfulEntries[] = $entry;
                } catch (QueryException $duplicateOrDatabaseException) {
                    $result['skipped']++;
                }
            }
        }

        $walletDeltas = [];
        foreach ($successfulEntries as $entry) {
            $memberId = $entry['member']->member_id;
            $walletDeltas[$memberId] = bcadd(
                $walletDeltas[$memberId] ?? '0.0000',
                $entry['income_amount'],
                self::MONEY_SCALE
            );
        }

        $this->applyWalletDeltas($walletDeltas);
        $result['generated'] += count($successfulEntries);

        return $result;
    }

    public function prepareForInvestments(iterable $memberIds): void
    {
        $membersById = $this->membersById();
        $rankMemberIds = [];

        foreach ($memberIds as $memberId) {
            $currentSponsorId = $membersById->get($memberId)?->sponsor_id;
            $visited = [$memberId => true];

            for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
                if (empty($currentSponsorId) || isset($visited[$currentSponsorId])) {
                    break;
                }

                $beneficiary = $membersById->get($currentSponsorId);
                if (! $beneficiary) {
                    break;
                }

                $visited[$beneficiary->member_id] = true;
                $rankMemberIds[$beneficiary->member_id] = true;
                $currentSponsorId = $beneficiary->sponsor_id;
            }
        }

        $uncachedMemberIds = array_values(array_diff(array_keys($rankMemberIds), array_keys($this->rankResultsByMember)));
        if ($uncachedMemberIds !== []) {
            $this->rankResultsByMember = array_replace(
                $this->rankResultsByMember,
                $this->rankService->calculateForMembers($uncachedMemberIds)
            );
        }
    }

    private function membersById(): Collection
    {
        return $this->membersById ??= Member::query()
            ->select(['member_id', 'sponsor_id', 'member_name'])
            ->get()
            ->keyBy('member_id');
    }

    private function rateForLevel(int $level): string
    {
        return $this->ratesByLevel[$level] ??= LevelCommissionRateResolver::forLevel($level);
    }

    private function applyWalletDeltas(array $walletDeltas): void
    {
        if ($walletDeltas === []) {
            return;
        }

        $caseSql = [];
        $bindings = [];
        foreach ($walletDeltas as $memberId => $delta) {
            $caseSql[] = 'WHEN ? THEN COALESCE(working_wallet_amount, 0) + ?';
            $bindings[] = $memberId;
            $bindings[] = $delta;
        }

        $memberPlaceholders = implode(', ', array_fill(0, count($walletDeltas), '?'));
        $bindings = array_merge($bindings, array_keys($walletDeltas));
        DB::update(
            'UPDATE members SET working_wallet_amount = CASE member_id ' . implode(' ', $caseSql) . ' ELSE working_wallet_amount END WHERE member_id IN (' . $memberPlaceholders . ')',
            $bindings
        );
    }
}
