<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\LevelCommissionGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateLevelCommission extends Command
{
    protected $signature = 'commission:generate-level {--date= : Business date (YYYY-MM-DD)} {--testing-period : Use a unique two-minute development testing period} {--investment= : Specific Investment ID to (re)process}';

    protected $description = 'Generate Level Commission for active investments that have not yet been processed.';

    public function handle(LevelCommissionGenerationService $service): int
    {
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

        $generated = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(100, function ($investments) use ($service, $businessDate, &$generated, &$skipped) {
            foreach ($investments as $investment) {
            $result = DB::transaction(fn () => $service->generateForInvestment($investment, $businessDate));
                $generated += $result['generated'];
                $skipped += $result['skipped'];
            }
        });

        $this->info("Level commission processing completed: {$generated} generated, {$skipped} skipped.");

        return self::SUCCESS;
    }

    protected function testingPeriodDate(): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Kolkata');
        $minutesSinceMidnight = $now->hour * 60 + $now->minute;

        return $now->startOfDay()->addDays(intdiv($minutesSinceMidnight, 2));
    }
}
