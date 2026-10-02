<?php

    namespace App\Console\Commands;

    use App\Services\RoiGenerationService;
    use Carbon\CarbonImmutable;
    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\Log;

    class GenerateRoi extends Command
    {
        protected $signature = 'roi:generate {--date= : Business date (YYYY-MM-DD) for a safe manual run} {--testing-period : Use a unique two-minute development testing period} {--investment-prefix= : Restrict processing to an investment ID prefix}';

        protected $description = 'Generate daily ROI for eligible investments.';

        public function handle(RoiGenerationService $roiGenerationService): int
        {
            $startedAt = microtime(true);
            Log::info('Scheduled ROI generation started.', ['testing_period' => $this->option('testing-period')]);

            $date = $this->option('testing-period')
                ? $this->testingPeriodDate()
                : ($this->option('date')
                ? CarbonImmutable::parse($this->option('date'), RoiGenerationService::TIMEZONE)
                : CarbonImmutable::now(RoiGenerationService::TIMEZONE));

            if ($this->option('testing-period') && ! app()->environment(['local', 'testing', 'staging'])) {
                $this->error('The --testing-period option is only available in local, testing, or staging environments.');

                return self::FAILURE;
            }

            $result = $roiGenerationService->generateForDate($date->startOfDay(), $this->option('investment-prefix'));

            $this->info("ROI processing completed for {$date->toDateString()}: {$result['generated']} generated, {$result['expired']} expired, {$result['skipped']} skipped.");
            Log::info('Scheduled ROI generation finished.', $result + ['duration_seconds' => round(microtime(true) - $startedAt, 3), 'business_date' => $date->toDateString()]);

            return self::SUCCESS;
        }

        protected function testingPeriodDate(): CarbonImmutable
        {
            $now = CarbonImmutable::now(RoiGenerationService::TIMEZONE);
            $minutesSinceMidnight = $now->hour * 60 + $now->minute;

            return $now->startOfDay()->addDays(intdiv($minutesSinceMidnight, 2));
        }
    }
