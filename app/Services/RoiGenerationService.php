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

    public function __construct(private readonly ReportSummaryService $reportSummaryService)
    {
    }

    public function generateForDate(CarbonImmutable $businessDate, ?string $investmentPrefix = null): array
    {
        $businessDate = $businessDate->setTimezone(self::TIMEZONE)->startOfDay();
        $result = ['generated' => 0, 'expired' => 0, 'skipped' => 0];

        $query = Investment::query()->where('status', 'active');

        if ($investmentPrefix !== null && $investmentPrefix !== '') {
            $query->where('investment_id', 'like', $investmentPrefix . '%');
        }

        $query
            ->orderBy('id')
            ->chunkById(100, function ($investments) use ($businessDate, &$result) {
                foreach ($investments as $investment) {
                    $outcome = $this->processInvestment($investment->id, $businessDate);
                    $result[$outcome]++;
                }
            });

        return $result;
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
                    ->whereDate('roi_date', $businessDate->toDateString())
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

                if (bccomp($remainingCap, '0', self::MONEY_SCALE) <= 0) {
                    $investment->update([
                        'status' => 'expired',
                        'closed_at' => now(),
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

                $daysToCap = (int) max(0, (float) ceil((float) bcdiv($cap, $dailyIncome, 8)));
                $investmentAgeInDays = $investmentDate->diffInDays($businessDate, false);
                if ($investmentAgeInDays >= $daysToCap && bccomp($combinedIncome, $cap, self::MONEY_SCALE) < 0) {
                    $investment->update([
                        'status' => 'expired',
                        'closed_at' => $businessDate->toDateTimeString(),
                        'closing_amount' => $cap,
                    ]);

                    return 'expired';
                }

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
                        'closed_at' => now(),
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

            return 'skipped';
        }
    }
}
