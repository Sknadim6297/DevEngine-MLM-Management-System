<?php

namespace Tests\Feature;

use App\Services\FinancialCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialCycleLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_skips_before_any_stage_when_another_process_holds_the_lock(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'financial-cycle-lock-' . uniqid('', true);
        $lockPath = $basePath . '.lock';
        $checkpointPath = $basePath . '.json';
        $logPath = $basePath . '.log';
        config([
            'financial.accelerated.enabled' => true,
            'financial.accelerated.lock_path' => $lockPath,
            'financial.accelerated.checkpoint_path' => $checkpointPath,
            'financial.accelerated.log_path' => $logPath,
        ]);

        $lock = fopen($lockPath, 'c');
        $this->assertNotFalse($lock);
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        try {
            $result = FinancialCycleService::make()->run();

            $this->assertSame(FinancialCycleService::STATUS_SKIPPED_LOCKED, $result['status']);
            $this->assertFileDoesNotExist($checkpointPath);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            foreach ([$lockPath, $checkpointPath, $logPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
