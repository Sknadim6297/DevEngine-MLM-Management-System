<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\RoiTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteInvestmentTo300 extends Command
{
    protected $signature = 'investment:complete-300 {investment_id : The investment ID to complete to its 300% total return cap}';

    protected $description = 'Testing-only command to complete an investment to the 300% cap without exceeding it. Local/testing only.';

    public function handle(): int
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->error('The investment:complete-300 command is a testing-only command and is disabled outside local/testing environments.');

            return self::FAILURE;
        }

        $investmentId = trim((string) $this->argument('investment_id'));

        if ($investmentId === '') {
            $this->error('An investment ID is required.');

            return self::FAILURE;
        }

        $investment = Investment::query()->where('investment_id', $investmentId)->first();

        if (! $investment) {
            $this->error("Investment {$investmentId} was not found.");

            return self::FAILURE;
        }

        $capAmount = bcmul((string) $investment->amount, '3', 4);
        $existingRoi = (string) RoiTransaction::query()->where('investment_id', $investmentId)->sum('income_amount');
        $existingLevelCommission = (string) LevelCommissionTransaction::query()->where('investment_id', $investmentId)->sum('income_amount');
        $currentCombinedReturn = bcadd($existingRoi, $existingLevelCommission, 4);

        $existingDummyRoi = RoiTransaction::query()
            ->where('investment_id', $investmentId)
            ->where('reference', 'like', 'TEST-%')
            ->exists();

        $existingDummyLevelCommission = LevelCommissionTransaction::query()
            ->where('investment_id', $investmentId)
            ->where('reference', 'like', 'TEST-%')
            ->exists();

        $remainingAmount = bcsub($capAmount, $currentCombinedReturn, 4);
        $dummyAmountAdded = '0.0000';

        if (bccomp($currentCombinedReturn, $capAmount, 4) >= 0) {
            $investment->status = 'expired';
            $investment->closed_at = $investment->closed_at ?? now();
            $investment->save();

            $this->printSummary($investment, $existingRoi, $existingLevelCommission, $currentCombinedReturn, '0.0000', '0.0000', 'expired');
            $this->info('Investment is already at or beyond the 300% cap. No new dummy transaction was added.');

            return self::SUCCESS;
        }

        if ($existingDummyRoi || $existingDummyLevelCommission) {
            $investment->status = 'expired';
            $investment->closed_at = $investment->closed_at ?? now();
            $investment->save();

            $this->printSummary($investment, $existingRoi, $existingLevelCommission, $currentCombinedReturn, $remainingAmount, '0.0000', 'expired');
            $this->warn('A testing dummy already exists for this investment. The command skipped creating a duplicate to keep the total at the 300% cap.');

            return self::SUCCESS;
        }

        $dummyRoiAmount = bcdiv($remainingAmount, '2', 4);
        $dummyLevelCommissionAmount = bcsub($remainingAmount, $dummyRoiAmount, 4);
        $dummyAmountAdded = bcadd($dummyRoiAmount, $dummyLevelCommissionAmount, 4);

        try {
            DB::transaction(function () use ($investment, $dummyRoiAmount, $dummyLevelCommissionAmount, $capAmount) {
                $roiReference = 'TEST-ROI-' . $investment->investment_id . '-' . now()->format('YmdHis');
                $levelReference = 'TEST-LC-' . $investment->investment_id . '-' . now()->format('YmdHis');

                RoiTransaction::query()->create([
                    'reference' => $roiReference,
                    'investment_id' => $investment->investment_id,
                    'member_id' => $investment->member_id,
                    'member_name' => $investment->member_name,
                    'on_amount' => $investment->amount,
                    'rate_percentage' => '5.000',
                    'income_amount' => $dummyRoiAmount,
                    'roi_date' => '2099-12-31',
                    'status' => 'generated',
                    'withdrawable_on' => '2099-12-31',
                ]);

                LevelCommissionTransaction::query()->create([
                    'reference' => $levelReference,
                    'investment_id' => $investment->investment_id,
                    'member_id' => $investment->member_id,
                    'member_name' => $investment->member_name,
                    'from_member_id' => 'ST666666',
                    'from_member_name' => 'Admin',
                    'level' => 1,
                    'business_date' => '2099-12-31',
                    'on_amount' => $investment->amount,
                    'rate_percentage' => '1.000',
                    'income_amount' => $dummyLevelCommissionAmount,
                ]);

                $finalCombinedReturn = bcadd(
                    bcadd((string) RoiTransaction::query()->where('investment_id', $investment->investment_id)->sum('income_amount'), (string) LevelCommissionTransaction::query()->where('investment_id', $investment->investment_id)->sum('income_amount'), 4),
                    '0.0000',
                    4
                );

                if (bccomp($finalCombinedReturn, $capAmount, 4) > 0) {
                    throw new \RuntimeException('The dummy transaction would exceed the 300% cap.');
                }

                $investment->status = 'expired';
                $investment->closed_at = now();
                $investment->save();
            });
        } catch (\Throwable $exception) {
            $this->error('Unable to complete the investment to the 300% cap safely: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $finalRoi = (string) RoiTransaction::query()->where('investment_id', $investmentId)->sum('income_amount');
        $finalLevelCommission = (string) LevelCommissionTransaction::query()->where('investment_id', $investmentId)->sum('income_amount');
        $finalCombinedReturn = bcadd($finalRoi, $finalLevelCommission, 4);

        $this->printSummary(
            $investment->fresh(),
            $existingRoi,
            $existingLevelCommission,
            $currentCombinedReturn,
            $remainingAmount,
            $dummyAmountAdded,
            $investment->fresh()->status
        );

        $this->info('Final combined return: ' . $finalCombinedReturn);
        $this->info('Final investment status: ' . $investment->fresh()->status);

        return self::SUCCESS;
    }

    protected function printSummary(
        Investment $investment,
        string $existingRoi,
        string $existingLevelCommission,
        string $currentCombinedReturn,
        string $remainingAmount,
        string $dummyAmountAdded,
        string $finalStatus
    ): void {
        $this->newLine();
        $this->info('Investment ID: ' . $investment->investment_id);
        $this->info('Investment amount: ' . $investment->amount);
        $this->info('Existing ROI: ' . $existingRoi);
        $this->info('Existing Level Commission: ' . $existingLevelCommission);
        $this->info('Current combined return: ' . $currentCombinedReturn);
        $this->info('Remaining amount: ' . $remainingAmount);
        $this->info('Dummy amount added: ' . $dummyAmountAdded);
        $this->info('Final combined return: ' . bcadd($currentCombinedReturn, $dummyAmountAdded, 4));
        $this->info('Final investment status: ' . $finalStatus);
        $this->newLine();
    }
}
