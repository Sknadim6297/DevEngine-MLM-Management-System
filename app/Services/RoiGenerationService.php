<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoiGenerationService
{
    public const TIMEZONE = 'Asia/Kolkata';

    private const MONTHLY_RATE_PERCENT = '5';

    private const CAP_MULTIPLIER = '3';

    private const MONEY_SCALE = 4;

    private const BATCH_SIZE = 500;

    private const INSERT_BATCH_SIZE = 500;

    public function __construct(private readonly ReportSummaryService $reportSummaryService)
    {
    }

    public function generateForDate(CarbonImmutable $businessDate, ?string $investmentPrefix = null): array
    {
        $businessDate = $businessDate->setTimezone(self::TIMEZONE)->startOfDay();
        $result = ['eligible_investments' => 0, 'generated' => 0, 'expired' => 0, 'skipped' => 0, 'failed' => 0];

        $query = Investment::query()->where('status', 'active');
        $firstEligibleTimestamp = $businessDate
            ->setTimezone(self::TIMEZONE)
            ->startOfDay()
            ->setTimezone('UTC');
        $query->where('created_at', '<', $firstEligibleTimestamp);

        if ($investmentPrefix !== null && $investmentPrefix !== '') {
            $query->where('investment_id', 'like', $investmentPrefix . '%');
        }

        $query
            ->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($investments) use ($businessDate, &$result) {
                $ids = $investments->pluck('id')->all();
                $result['eligible_investments'] += count($ids);

                try {
                    $outcomes = $this->processBatch($ids, $businessDate);
                } catch (\Throwable $exception) {
                    // The batch rolled back as a unit; isolate failures by retrying one investment at a time.
                    Log::warning('ROI batch failed; retrying investments individually.', [
                        'roi_date' => $businessDate->toDateString(),
                        'exception' => $exception->getMessage(),
                    ]);
                    $outcomes = [];
                    foreach ($ids as $id) {
                        $outcome = $this->processInvestment($id, $businessDate);
                        $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
                    }
                }

                foreach ($outcomes as $outcome => $count) {
                    $result[$outcome] += $count;
                }
            });

        return $result;
    }

    /**
     * Processes a batch of investments in a single transaction with the same rules as processInvestment().
     *
     * @param  array<int, int>  $investmentIds
     * @return array<string, int>
     */
    private function processBatch(array $investmentIds, CarbonImmutable $businessDate): array
    {
        return DB::transaction(function () use ($investmentIds, $businessDate): array {
            $outcomes = ['generated' => 0, 'expired' => 0, 'skipped' => 0];
            $dateString = $businessDate->toDateString();
            $timestamp = now()->toDateTimeString();

            sort($investmentIds);
            $investments = Investment::query()
                ->whereIn('id', $investmentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $outcomes['skipped'] += count($investmentIds) - $investments->count();
            $eligible = $investments->filter(function (Investment $investment) use ($businessDate, &$outcomes): bool {
                $investmentDate = CarbonImmutable::instance($investment->created_at)
                    ->setTimezone(self::TIMEZONE)
                    ->startOfDay();
                if ($investment->status !== 'active' || $businessDate->lessThanOrEqualTo($investmentDate)) {
                    $outcomes['skipped']++;

                    return false;
                }

                return true;
            });

            if ($eligible->isEmpty()) {
                return $outcomes;
            }

            $publicIds = $eligible->pluck('investment_id')->all();
            $alreadyGenerated = RoiTransaction::query()
                ->whereIn('investment_id', $publicIds)
                ->where('roi_date', $dateString)
                ->pluck('investment_id')
                ->flip();
            $roiIncome = RoiTransaction::query()
                ->whereIn('investment_id', $publicIds)
                ->groupBy('investment_id')
                ->selectRaw('investment_id, SUM(income_amount) AS total')
                ->pluck('total', 'investment_id');
            $levelIncome = LevelCommissionTransaction::query()
                ->whereIn('investment_id', $publicIds)
                ->groupBy('investment_id')
                ->selectRaw('investment_id, SUM(income_amount) AS total')
                ->pluck('total', 'investment_id');
            $members = Member::query()
                ->whereIn('member_id', $eligible->pluck('member_id')->unique()->sort()->values()->all())
                ->orderBy('member_id')
                ->lockForUpdate()
                ->get(['id', 'member_id', 'member_name'])
                ->keyBy('member_id');

            $rows = [];
            $walletDeltas = [];
            $expirations = [];
            foreach ($eligible as $investment) {
                if ($alreadyGenerated->has($investment->investment_id)) {
                    $outcomes['skipped']++;
                    continue;
                }

                $member = $members->get($investment->member_id);
                if (! $member) {
                    Log::warning('ROI skipped because investment member was not found.', [
                        'investment_id' => $investment->investment_id,
                        'member_id' => $investment->member_id,
                        'roi_date' => $dateString,
                    ]);
                    $outcomes['skipped']++;
                    continue;
                }

                $cap = bcmul((string) $investment->amount, self::CAP_MULTIPLIER, self::MONEY_SCALE);
                $combinedIncome = bcadd(
                    (string) ($roiIncome[$investment->investment_id] ?? '0'),
                    (string) ($levelIncome[$investment->investment_id] ?? '0'),
                    self::MONEY_SCALE
                );
                $remainingCap = bcsub($cap, $combinedIncome, self::MONEY_SCALE);

                if (bccomp($remainingCap, '0', self::MONEY_SCALE) <= 0) {
                    $investment->update([
                        'status' => 'expired',
                        'closed_at' => $timestamp,
                        'closing_amount' => $combinedIncome,
                    ]);
                    $outcomes['expired']++;
                    continue;
                }

                $dailyIncome = bcdiv(bcmul((string) $investment->amount, '0.05', 8), '30', self::MONEY_SCALE);
                $incomeAmount = bccomp($dailyIncome, $remainingCap, self::MONEY_SCALE) > 0 ? $remainingCap : $dailyIncome;
                if (bccomp($incomeAmount, '0', self::MONEY_SCALE) <= 0) {
                    $outcomes['skipped']++;
                    continue;
                }

                $rows[] = [
                    'reference' => 'ROI-' . $investment->investment_id . '-' . $businessDate->format('Ymd'),
                    'investment_id' => $investment->investment_id,
                    'member_id' => $member->member_id,
                    'member_name' => $member->member_name,
                    'on_amount' => $investment->amount,
                    'rate_percentage' => self::MONTHLY_RATE_PERCENT,
                    'income_amount' => $incomeAmount,
                    'roi_date' => $dateString,
                    'status' => 'generated',
                    'withdrawable_on' => $businessDate->addMonthNoOverflow()->startOfMonth()->toDateString(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $walletDeltas[$member->member_id] = bcadd($walletDeltas[$member->member_id] ?? '0', $incomeAmount, self::MONEY_SCALE);

                if (bccomp(bcadd($combinedIncome, $incomeAmount, self::MONEY_SCALE), $cap, self::MONEY_SCALE) >= 0) {
                    $investment->update([
                        'status' => 'expired',
                        'closed_at' => $timestamp,
                        'closing_amount' => $cap,
                    ]);
                    $outcomes['expired']++;
                } else {
                    $outcomes['generated']++;
                }
            }

            foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $batch) {
                RoiTransaction::query()->insert($batch);
            }

            if ($rows !== [] && $this->reportSummaryService->summaryWritesActive()) {
                foreach (array_chunk(array_column($rows, 'reference'), 1000) as $references) {
                    foreach (RoiTransaction::query()->whereIn('reference', $references)->get() as $transaction) {
                        $this->reportSummaryService->addRoiTransaction($transaction);
                    }
                }
            }

            foreach (array_chunk($walletDeltas, self::INSERT_BATCH_SIZE, true) as $batch) {
                $caseSql = [];
                $bindings = [];
                foreach ($batch as $memberId => $delta) {
                    $caseSql[] = 'WHEN ? THEN COALESCE(roi_wallet_amount, 0) + ?';
                    $bindings[] = $memberId;
                    $bindings[] = $delta;
                }
                $bindings[] = $timestamp;
                $bindings = array_merge($bindings, array_keys($batch));
                DB::update(
                    'UPDATE members SET roi_wallet_amount = CASE member_id ' . implode(' ', $caseSql) . ' ELSE roi_wallet_amount END, updated_at = ? WHERE member_id IN (' . implode(', ', array_fill(0, count($batch), '?')) . ')',
                    $bindings
                );
            }

            return $outcomes;
        }, 3);
    }

    private function processInvestment(int $investmentId, CarbonImmutable $businessDate): string
    {
        try {
            return DB::transaction(function () use ($investmentId, $businessDate) {
                $investment = Investment::query()->lockForUpdate()->find($investmentId);

                if (! $investment || $investment->status !== 'active') {
                    return 'skipped';
                }

                if ($investment->status === 'expired') {
                    return 'skipped';
                }

                $investmentDate = CarbonImmutable::instance($investment->created_at)
                    ->setTimezone(self::TIMEZONE)
                    ->startOfDay();

                if ($businessDate->lessThanOrEqualTo($investmentDate)) {
                    return 'skipped';
                }

                if (RoiTransaction::query()
                    ->where('investment_id', $investment->investment_id)
                    ->where('roi_date', $businessDate->toDateString())
                    ->exists()) {
                    return 'skipped';
                }

                $member = Member::query()
                    ->where('member_id', $investment->member_id)
                    ->lockForUpdate()
                    ->first();

                if (! $member) {
                    Log::warning('ROI skipped because investment member was not found.', [
                        'investment_id' => $investment->investment_id,
                        'member_id' => $investment->member_id,
                        'roi_date' => $businessDate->toDateString(),
                    ]);

                    return 'skipped';
                }

                $cap = bcmul((string) $investment->amount, self::CAP_MULTIPLIER, self::MONEY_SCALE);
                $roiIncome = (string) RoiTransaction::query()
                    ->where('investment_id', $investment->investment_id)
                    ->sum('income_amount');
                $workingIncome = (string) LevelCommissionTransaction::query()
                    ->where('investment_id', $investment->investment_id)
                    ->sum('income_amount');
                $combinedIncome = bcadd($roiIncome, $workingIncome, self::MONEY_SCALE);
                $remainingCap = bcsub($cap, $combinedIncome, self::MONEY_SCALE);
                $transactionTimestamp = now()->toDateTimeString();

                if (bccomp($remainingCap, '0', self::MONEY_SCALE) <= 0) {
                    $investment->update([
                        'status' => 'expired',
                        'closed_at' => $transactionTimestamp,
                        'closing_amount' => $combinedIncome,
                    ]);

                    return 'expired';
                }

                // 5% monthly ROI divided by a fixed 30-day divisor.
                $dailyIncome = bcdiv(
                    bcmul((string) $investment->amount, '0.05', 8),
                    '30',
                    self::MONEY_SCALE
                );

                $incomeAmount = bccomp($dailyIncome, $remainingCap, self::MONEY_SCALE) > 0
                    ? $remainingCap
                    : $dailyIncome;

                if (bccomp($incomeAmount, '0', self::MONEY_SCALE) <= 0) {
                    return 'skipped';
                }

                $roiTransaction = RoiTransaction::create([
                    'reference' => 'ROI-'.$investment->investment_id.'-'.$businessDate->format('Ymd'),
                    'investment_id' => $investment->investment_id,
                    'member_id' => $member->member_id,
                    'member_name' => $member->member_name,
                    'on_amount' => $investment->amount,
                    'rate_percentage' => self::MONTHLY_RATE_PERCENT,
                    'income_amount' => $incomeAmount,
                    'roi_date' => $businessDate->toDateString(),
                    'status' => 'generated',
                    'withdrawable_on' => $businessDate->addMonthNoOverflow()->startOfMonth()->toDateString(),
                    'created_at' => $transactionTimestamp,
                    'updated_at' => $transactionTimestamp,
                ]);

                $this->reportSummaryService->addRoiTransaction($roiTransaction);

                $member->roi_wallet_amount = bcadd(
                    (string) $member->roi_wallet_amount,
                    $incomeAmount,
                    self::MONEY_SCALE
                );
                $member->save();

                $reachesCap = bccomp(bcadd($combinedIncome, $incomeAmount, self::MONEY_SCALE), $cap, self::MONEY_SCALE) >= 0;

                if ($reachesCap) {
                    $investment->update([
                        'status' => 'expired',
                        'closed_at' => $transactionTimestamp,
                        'closing_amount' => $cap,
                    ]);
                }

                Log::info('ROI generated.', [
                    'investment_id' => $investment->investment_id,
                    'member_id' => $member->member_id,
                    'roi_date' => $businessDate->toDateString(),
                    'status' => $reachesCap ? 'expired' : 'generated',
                ]);

                return $reachesCap ? 'expired' : 'generated';
            }, 3);
        } catch (\Throwable $exception) {
            Log::error('ROI generation failed.', [
                'investment_database_id' => $investmentId,
                'roi_date' => $businessDate->toDateString(),
                'exception' => $exception->getMessage(),
            ]);

            return 'failed';
        }
    }

}
