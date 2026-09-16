<?php

namespace App\Console\Commands;

use App\Services\RoiGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRoi extends Command
{
    protected $signature = 'roi:generate {--date= : Business date (YYYY-MM-DD) for a safe manual run}';

    protected $description = 'Generate daily ROI for eligible investments.';

    public function handle(RoiGenerationService $roiGenerationService): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), RoiGenerationService::TIMEZONE)
            : CarbonImmutable::now(RoiGenerationService::TIMEZONE);

        $result = $roiGenerationService->generateForDate($date->startOfDay());

        $this->info("ROI processing completed for {$date->toDateString()}: {$result['generated']} generated, {$result['expired']} expired, {$result['skipped']} skipped.");

        return self::SUCCESS;
    }
}
