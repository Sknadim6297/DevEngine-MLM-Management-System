<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\LevelCommissionGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateLevelCommission extends Command
{
    protected $signature = 'commission:generate-level {--date= : Business date (YYYY-MM-DD)} {--testing-period : Use a unique two-minute development testing period} {--investment= : Specific Investment ID to (re)process} {--investment-prefix= : Restrict processing to an investment ID prefix}';

    protected $description = 'Generate Level Commission for active investments that have not yet been processed.';

    public function handle(LevelCommissionGenerationService $service): int
    {
        $startedAt = microtime(true);
        Log::info('Scheduled level commission generation started.', ['testing_period' => $this->option('testing-period')]);

        $businessDate = $this->option('testing-period')
            ? $this->testingPeriodDate()
            : ($this->option('date')
            ? CarbonImmutable::parse($this->option('date'), 'Asia/Kolkata')->startOfDay()
            : CarbonImmutable::now('Asia/Kolkata')->startOfDay());

        if ($this->option('testing-period') && ! app()->environment(['local', 'testing'])) {
            $this->error('The --testing-period option is only available in local or testing environments.');

            return self::FAILURE;
        }

        $query = Investment::query()->where('status', 'active');

        if ($investmentId = $this->option('investment')) {
            $query->where('investment_id', $investmentId);
        }

        if ($investmentPrefix = $this->option('investment-prefix')) {
            $query->where('investment_id', 'like', $investmentPrefix . '%');
        }

        $service->prepareForInvestments((clone $query)->pluck('member_id'));

        $generated = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(100, function ($investments) use ($service, $businessDate, &$generated, &$skipped) {
            DB::transaction(function () use ($investments, $service, $businessDate, &$generated, &$skipped): void {
                foreach ($investments as $investment) {
                    $result = $service->generateForInvestment($investment, $businessDate);
                    $generated += $result['generated'];
                    $skipped += $result['skipped'];
                }
            });
        });

        $this->info("Level commission processing completed: {$generated} generated, {$skipped} skipped.");
        Log::info('Scheduled level commission generation finished.', ['generated' => $generated, 'skipped' => $skipped, 'duration_seconds' => round(microtime(true) - $startedAt, 3), 'business_date' => $businessDate->toDateString()]);

        return self::SUCCESS;
    }

    protected function testingPeriodDate(): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Kolkata');
        $minutesSinceMidnight = $now->hour * 60 + $now->minute;

        return $now->startOfDay()->addDays(intdiv($minutesSinceMidnight, 2));
    }
}
