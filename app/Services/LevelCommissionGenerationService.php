<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Credits upline members' Working Wallet with Level Commission for a qualifying investment.
 *
 * Base amount: the investment amount (on_amount), matching the existing
 * RoiTransaction pattern for the same investment_id relationship.
 * Wallet destination: working_wallet_amount, matching RoiGenerationService's
 * "workingIncome" naming and ActivationWalletController's existing
 * "Transfer from Working Wallet" logic.
 * Duplicate protection: unique(investment_id, member_id, business_date) on level_commission_transactions.
 */
class LevelCommissionGenerationService
{
    private const MONEY_SCALE = 4;

    private const MAX_LEVEL = 32;

    private ?Collection $membersById = null;

    private array $ratesByLevel = [];

    private array $rankResultsByMember = [];

    public function __construct(
        private readonly RankService $rankService,
        private readonly ReportSummaryService $reportSummaryService,
    ) {
    }

    private const INSERT_BATCH_SIZE = 500;

    private const WALLET_BATCH_SIZE = 500;

    public function generateForInvestment(Investment $investment, ?CarbonImmutable $businessDate = null): array
    {
        return $this->generateForInvestments([$investment], $businessDate);
    }

    /**
     * Generates Level Commission for a batch of investments inside one database transaction.
     * Rules are identical to the per-investment flow: rank-unlocked levels, configured rates,
     * unique (investment, beneficiary, date) protection and the combined 300% investment cap.
     *
     * @param  iterable<Investment>  $investments
     * @return array{generated: int, skipped: int, reasons: array<string, int>}
     */
    public function generateForInvestments(iterable $investments, ?CarbonImmutable $businessDate = null): array
    {
        $businessDate ??= CarbonImmutable::now('Asia/Kolkata')->startOfDay();
        $result = ['generated' => 0, 'skipped' => 0, 'reasons' => []];
        $skip = function (string $reason, int $count = 1) use (&$result): void {
            $result['skipped'] += $count;
            $result['reasons'][$reason] = ($result['reasons'][$reason] ?? 0) + $count;
        };

        $plans = [];
        foreach ($investments as $investment) {
            if ($investment->status !== 'active') {
                continue;
            }

            $plan = $this->planForInvestment($investment, $skip);
            if ($plan !== []) {
                $plans[$investment->id] = ['investment' => $investment, 'entries' => $plan];
            }
        }

        if ($plans === []) {
            return $result;
        }

        $dateString = $businessDate->toDateString();
        $dateSuffix = $businessDate->format('Ymd');
        $timestamp = now()->toDateTimeString();

        $generated = DB::transaction(function () use ($plans, $dateString, $dateSuffix, $timestamp, $skip): int {
            $investmentIds = array_keys($plans);
            sort($investmentIds);
            $locked = Investment::query()
                ->whereIn('id', $investmentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $publicIds = $locked->where('status', 'active')->pluck('investment_id')->all();
            if ($publicIds === []) {
                return 0;
            }

            $existing = [];
            $roiIncome = [];
            $levelIncome = [];
            foreach (array_chunk($publicIds, 1000) as $idChunk) {
                foreach (DB::table('level_commission_transactions')
                    ->whereIn('investment_id', $idChunk)
                    ->where('business_date', $dateString)
                    ->get(['investment_id', 'member_id']) as $row) {
                    $existing[$row->investment_id . '|' . $row->member_id] = true;
                }
                foreach (RoiTransaction::query()
                    ->whereIn('investment_id', $idChunk)
                    ->groupBy('investment_id')
                    ->selectRaw('investment_id, SUM(income_amount) AS total')
                    ->pluck('total', 'investment_id') as $id => $total) {
                    $roiIncome[$id] = (string) $total;
                }
                foreach (LevelCommissionTransaction::query()
                    ->whereIn('investment_id', $idChunk)
                    ->groupBy('investment_id')
                    ->selectRaw('investment_id, SUM(income_amount) AS total')
                    ->pluck('total', 'investment_id') as $id => $total) {
                    $levelIncome[$id] = (string) $total;
                }
            }

            $rows = [];
            $toExpire = [];
            foreach ($investmentIds as $id) {
                $lockedInvestment = $locked->get($id);
                if (! $lockedInvestment || $lockedInvestment->status !== 'active') {
                    continue;
                }

                $publicId = $lockedInvestment->investment_id;
                $candidates = [];
                foreach ($plans[$id]['entries'] as $entry) {
                    if (isset($existing[$publicId . '|' . $entry['member']->member_id])) {
                        $skip('duplicate_already_generated');
                        continue;
                    }
                    $candidates[] = $entry;
                }

                if ($candidates === []) {
                    continue;
                }

                $cap = bcmul((string) $lockedInvestment->amount, '3', self::MONEY_SCALE);
                $remainingCap = bcsub(
                    $cap,
                    bcadd($roiIncome[$publicId] ?? '0', $levelIncome[$publicId] ?? '0', self::MONEY_SCALE),
                    self::MONEY_SCALE
                );

                if (bccomp($remainingCap, '0', self::MONEY_SCALE) <= 0) {
                    $toExpire[$id] = $cap;
                    $skip('income_cap_reached', count($candidates));
                    continue;
                }

                $added = 0;
                foreach ($candidates as $position => $entry) {
                    if (bccomp($remainingCap, '0', self::MONEY_SCALE) <= 0) {
                        $skip('income_cap_reached', count($candidates) - $position);
                        break;
                    }

                    $calculatedIncome = bcdiv(
                        bcmul((string) $lockedInvestment->amount, $entry['rate'], 8),
                        '100',
                        self::MONEY_SCALE
                    );
                    $incomeAmount = bccomp($calculatedIncome, $remainingCap, self::MONEY_SCALE) > 0
                        ? $remainingCap
                        : $calculatedIncome;
                    if (bccomp($incomeAmount, '0', self::MONEY_SCALE) <= 0) {
                        $skip('zero_amount');
                        continue;
                    }

                    $member = $entry['member'];
                    $rows[] = [
                        'reference' => 'LC-' . $publicId . '-' . $member->member_id . '-' . $dateSuffix,
                        'investment_id' => $publicId,
                        'member_id' => $member->member_id,
                        'member_name' => $member->member_name,
                        'from_member_id' => $lockedInvestment->member_id,
                        'from_member_name' => $lockedInvestment->member_name,
                        'level' => $entry['level'],
                        'business_date' => $dateString,
                        'on_amount' => $lockedInvestment->amount,
                        'rate_percentage' => $entry['rate'],
                        'income_amount' => $incomeAmount,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                    $remainingCap = bcsub($remainingCap, $incomeAmount, self::MONEY_SCALE);
                    $added++;
                }

                if ($added > 0 && bccomp($remainingCap, '0', self::MONEY_SCALE) <= 0) {
                    $toExpire[$id] = $cap;
                }
            }

            $insertedRows = $this->insertRows($rows, $skip);

            if ($insertedRows !== []) {
                if ($this->reportSummaryService->summaryWritesActive()) {
                    $references = array_column($insertedRows, 'reference');
                    $transactionIds = [];
                    foreach (array_chunk($references, 1000) as $referenceChunk) {
                        $transactionIds = array_merge(
                            $transactionIds,
                            LevelCommissionTransaction::query()->whereIn('reference', $referenceChunk)->pluck('id')->all()
                        );
                    }
                    if (count($transactionIds) !== count($references)) {
                        throw new RuntimeException('Could not resolve every inserted Level Commission transaction for summary updates.');
                    }
                    foreach (array_chunk($transactionIds, 1000) as $idChunk) {
                        $this->reportSummaryService->addLevelCommissionTransactions($idChunk);
                    }
                }

                $walletDeltas = [];
                foreach ($insertedRows as $row) {
                    $walletDeltas[$row['member_id']] = bcadd(
                        $walletDeltas[$row['member_id']] ?? '0.0000',
                        $row['income_amount'],
                        self::MONEY_SCALE
                    );
                }
                $this->applyWalletDeltas($walletDeltas);
            }

            foreach ($toExpire as $id => $cap) {
                $investment = $locked->get($id);
                $investment->update([
                    'status' => 'expired',
                    'closed_at' => $investment->closed_at ?? $timestamp,
                    'closing_amount' => $cap,
                ]);
            }

            return count($insertedRows);
        }, 3);

        $result['generated'] += $generated;

        return $result;
    }

    /**
     * Builds the rank-eligible beneficiary entries for one investment, recording skip reasons.
     */
    private function planForInvestment(Investment $investment, callable $skip): array
    {
        $membersById = $this->membersById();
        $sourceMember = $membersById->get($investment->member_id);

        if (! $sourceMember) {
            $skip('source_member_missing');

            return [];
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
            $skip('no_sponsor_chain');

            return [];
        }

        $uncachedMemberIds = array_values(array_diff(
            array_map(fn (array $entry): string => $entry['member']->member_id, $chain),
            array_keys($this->rankResultsByMember)
        ));
        if ($uncachedMemberIds !== []) {
            $this->cacheUnlockedLevels($uncachedMemberIds);
        }

        $entries = [];
        foreach ($chain as $entry) {
            if ($entry['level'] > $this->rankResultsByMember[$entry['member']->member_id]['unlocked_levels']) {
                $skip('rank_level_locked');
                continue;
            }

            $rate = $this->rateForLevel($entry['level']);
            if (bccomp($rate, '0', 4) <= 0) {
                $skip('zero_rate');
                continue;
            }

            $incomeAmount = bcdiv(bcmul((string) $investment->amount, $rate, 8), '100', self::MONEY_SCALE);
            if (bccomp($incomeAmount, '0', self::MONEY_SCALE) <= 0) {
                $skip('zero_amount');
                continue;
            }

            $entries[] = ['member' => $entry['member'], 'level' => $entry['level'], 'rate' => $rate];
        }

        return $entries;
    }

    /**
     * Bulk-inserts rows; on a duplicate key falls back to row-by-row inserts so only
     * genuinely inserted rows are credited to wallets.
     */
    private function insertRows(array $rows, callable $skip): array
    {
        $inserted = [];
        foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $batch) {
            try {
                LevelCommissionTransaction::query()->insert($batch);
                array_push($inserted, ...$batch);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateKeyException($exception)) {
                    throw $exception;
                }

                foreach ($batch as $row) {
                    try {
                        LevelCommissionTransaction::query()->insert([$row]);
                        $inserted[] = $row;
                    } catch (QueryException $rowException) {
                        if (! $this->isDuplicateKeyException($rowException)) {
                            throw $rowException;
                        }

                        $skip('duplicate_already_generated');
                    }
                }
            }
        }

        return $inserted;
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->getMessage());

        return $driverCode === 1062
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }

    public function prepareForInvestments(iterable $memberIds, ?CarbonImmutable $businessDate = null): void
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
            $this->cacheUnlockedLevels($uncachedMemberIds);
        }
    }

    private function cacheUnlockedLevels(array $memberIds, ?string $toDate = null): void
    {
        foreach ($this->rankService->calculateForMembers($memberIds, null, $toDate) as $memberId => $rankData) {
            $this->rankResultsByMember[$memberId] = ['unlocked_levels' => $rankData['unlocked_levels']];
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
        foreach (array_chunk($walletDeltas, self::WALLET_BATCH_SIZE, true) as $batch) {
            $caseSql = [];
            $bindings = [];
            foreach ($batch as $memberId => $delta) {
                $caseSql[] = 'WHEN ? THEN COALESCE(working_wallet_amount, 0) + ?';
                $bindings[] = $memberId;
                $bindings[] = $delta;
            }

            $memberPlaceholders = implode(', ', array_fill(0, count($batch), '?'));
            $bindings = array_merge($bindings, array_keys($batch));
            DB::update(
                'UPDATE members SET working_wallet_amount = CASE member_id ' . implode(' ', $caseSql) . ' ELSE working_wallet_amount END WHERE member_id IN (' . $memberPlaceholders . ')',
                $bindings
            );
        }
    }
}