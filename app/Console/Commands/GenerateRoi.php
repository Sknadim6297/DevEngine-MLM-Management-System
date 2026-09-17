<?php

namespace App\Console\Commands;

use App\Services\RoiGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRoi extends Command
{
    protected $signature = 'roi:generate {--date= : Business date (YYYY-MM-DD) for a safe manual run} {--testing-period : Use a unique two-minute development testing period}';

    protected $description = 'Generate daily ROI for eligible investments.';

    public function handle(RoiGenerationService $roiGenerationService): int
    {
        $date = $this->option('testing-period')
            ? $this->testingPeriodDate()
            : ($this->option('date')
            ? CarbonImmutable::parse($this->option('date'), RoiGenerationService::TIMEZONE)
            : CarbonImmutable::now(RoiGenerationService::TIMEZONE));

        if ($this->option('testing-period') && ! app()->environment(['local', 'testing'])) {
            $this->error('The --testing-period option is only available in local or testing environments.');

            return self::FAILURE;
        }

        $result = $roiGenerationService->generateForDate($date->startOfDay());

        $this->info("ROI processing completed for {$date->toDateString()}: {$result['generated']} generated, {$result['expired']} expired, {$result['skipped']} skipped.");

        return self::SUCCESS;
    }

    protected function testingPeriodDate(): CarbonImmutable
    {
        $now = CarbonImmutable::now(RoiGenerationService::TIMEZONE);
        $minutesSinceMidnight = $now->hour * 60 + $now->minute;

        return $now->startOfDay()->addDays(intdiv($minutesSinceMidnight, 2));
    }
}
