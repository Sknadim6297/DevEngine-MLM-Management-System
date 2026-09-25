<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StressCleanup extends Command
{
    protected $signature = 'stress:cleanup {--force : Confirm deletion of only the registered stress dataset}';
    protected $description = 'Remove only the registered 10,000-member stress-test dataset and its generated records.';

    public function handle(): int
    {
        $memberIds = Member::query()->where('member_id', 'like', StressSeed::MEMBER_PREFIX . '%')->pluck('member_id');
        $investmentIds = Investment::query()->where('investment_id', 'like', StressSeed::INVESTMENT_PREFIX . '%')->pluck('investment_id');
        $counts = [
            'members' => $memberIds->count(),
            'investments' => $investmentIds->count(),
            'roi_transactions' => RoiTransaction::query()->whereIn('investment_id', $investmentIds)->count(),
            'level_commission_transactions' => LevelCommissionTransaction::query()->whereIn('investment_id', $investmentIds)->count(),
            'rank_achievements' => RankAchievement::query()->whereIn('member_id', $memberIds)->count(),
        ];
        $this->line('Records selected for dataset ' . StressSeed::DATASET_ID . ': ' . json_encode($counts));
        $this->line('Aisha ST123457 and all records outside the reserved prefixes are excluded.');
        if (! $this->option('force')) {
            $this->error('Nothing was deleted. Re-run with --force to confirm destructive cleanup.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($memberIds, $investmentIds): void {
            RankAchievement::query()->whereIn('member_id', $memberIds)->delete();
            LevelCommissionTransaction::query()->whereIn('investment_id', $investmentIds)->delete();
            RoiTransaction::query()->whereIn('investment_id', $investmentIds)->delete();
            Investment::query()->whereIn('investment_id', $investmentIds)->delete();
            Member::query()->whereIn('member_id', $memberIds)->delete();
            DB::table('stress_test_datasets')->where('dataset_id', StressSeed::DATASET_ID)->delete();
        });

        $this->info('Stress dataset cleanup completed.');
        return self::SUCCESS;
    }
}