<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\LevelCommissionGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateLevelCommission extends Command
{
    protected $signature = 'commission:generate-level {--investment= : Specific Investment ID to (re)process}';

    protected $description = 'Generate Level Commission for active investments that have not yet been processed.';

    public function handle(LevelCommissionGenerationService $service): int
    {
        $query = Investment::query()->where('status', 'active');

        if ($investmentId = $this->option('investment')) {
            $query->where('investment_id', $investmentId);
        }

        $generated = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(100, function ($investments) use ($service, &$generated, &$skipped) {
            foreach ($investments as $investment) {
                $result = DB::transaction(fn () => $service->generateForInvestment($investment));
                $generated += $result['generated'];
                $skipped += $result['skipped'];
            }
        });

        $this->info("Level commission processing completed: {$generated} generated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
