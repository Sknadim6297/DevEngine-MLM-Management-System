<?php

namespace App\Services;

use App\Models\LevelCommissionTransaction;
use App\Models\RoiTransaction;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class ReportSummaryService
{
    public function addRoiTransaction(RoiTransaction $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('roi_transactions')
            ->select(['member_id', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction->getKey())
            ->first();

        if (! $row) {
            throw new RuntimeException('The ROI transaction must be inserted before its report summary is updated.');
        }

        $this->increment('roi_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->increment('roi_report_member_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function addLevelCommissionTransaction(LevelCommissionTransaction $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('level_commission_transactions')
            ->select(['member_id', 'level', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction->getKey())
            ->first();

        if (! $row) {
            throw new RuntimeException('The Level Commission transaction must be inserted before its report summary is updated.');
        }

        $this->increment('level_commission_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->increment('level_commission_report_level_daily_summaries', [
            'generation_id' => $generationId,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->increment('level_commission_report_member_level_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function removeRoiTransaction(RoiTransaction|int $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('roi_transactions')
            ->select(['id', 'member_id', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction instanceof RoiTransaction ? $transaction->getKey() : $transaction)
            ->first();

        if (! $row) {
            throw new RuntimeException('The ROI transaction was not found before summary removal.');
        }

        $this->decrement('roi_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->decrement('roi_report_member_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function removeLevelCommissionTransaction(LevelCommissionTransaction|int $transaction): void
    {
        $generationId = $this->writableGenerationId();
        if ($generationId === null) {
            return;
        }

        $row = DB::table('level_commission_transactions')
            ->select(['id', 'member_id', 'level', 'income_amount'])
            ->selectRaw($this->dateKeyExpression() . ' AS date_key')
            ->where('id', $transaction instanceof LevelCommissionTransaction ? $transaction->getKey() : $transaction)
            ->first();

        if (! $row) {
            throw new RuntimeException('The Level Commission transaction was not found before summary removal.');
        }

        $this->decrement('level_commission_report_global_daily_summaries', [
            'generation_id' => $generationId,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->decrement('level_commission_report_level_daily_summaries', [
            'generation_id' => $generationId,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);

        $this->decrement('level_commission_report_member_level_daily_summaries', [
            'generation_id' => $generationId,
            'member_id' => $row->member_id,
            'level' => $row->level,
            'date_key' => $row->date_key,
        ], (string) $row->income_amount);
    }

    public function activeGenerationId(): ?int
    {
        $generationId = DB::table('report_summary_state')
            ->where('id', 1)
            ->value('active_generation_id');

        return $generationId === null ? null : (int) $generationId;
    }

    private function writableGenerationId(): ?int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Report summaries must be updated in the raw transaction transaction.');
        }

        $stateQuery = DB::table('report_summary_state')->where('id', 1);
        if (DB::connection()->getDriverName() === 'mysql') {
            $stateQuery->lock('lock in share mode');
        }
        $state = $stateQuery->first();

        if (! $state) {
            throw new RuntimeException('Report summary state has not been initialized.');
        }

        if ($state->rebuild_in_progress) {
            throw new RuntimeException('Transaction generation is paused while report summaries are being rebuilt.');
        }

        if ($state->active_generation_id === null) {
            return null;
        }

        if (! $state->writes_enabled) {
            throw new RuntimeException('Report summary writes are disabled for the active generation.');
        }

        return (int) $state->active_generation_id;
    }

    private function increment(string $table, array $dimensions, string $amount): void
    {
        $columns = array_keys($dimensions);
        $quotedColumns = implode(', ', array_map(fn (string $column): string => '`' . $column . '`', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $bindings = array_values($dimensions);
        $bindings[] = 1;
        $bindings[] = $amount;

        $sql = 'INSERT INTO `' . $table . '` (' . $quotedColumns . ', `transaction_count`, `total_income_amount`) VALUES ('
            . $placeholders . ', ?, ?)';

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(
                $sql . ' ON CONFLICT (' . $quotedColumns . ') DO UPDATE SET '
                    . '`transaction_count` = `transaction_count` + excluded.`transaction_count`, '
                    . '`total_income_amount` = `total_income_amount` + excluded.`total_income_amount`',
                $bindings
            );

            return;
        }

        DB::statement(
            $sql . ' ON DUPLICATE KEY UPDATE '
                . '`transaction_count` = `transaction_count` + VALUES(`transaction_count`), '
                . '`total_income_amount` = `total_income_amount` + VALUES(`total_income_amount`)',
            $bindings
        );
    }

    private function decrement(string $table, array $dimensions, string $amount): void
    {
        $query = DB::table($table);
        foreach ($dimensions as $column => $value) {
            $query->where($column, $value);
        }

        $updated = $query
            ->where('transaction_count', '>', 0)
            ->update([
                'transaction_count' => DB::raw('transaction_count - 1'),
                'total_income_amount' => DB::raw('total_income_amount - ' . DB::connection()->getPdo()->quote($amount)),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('The report summary row is missing or has an invalid count.');
        }

        $emptyBucket = DB::table($table);
        foreach ($dimensions as $column => $value) {
            $emptyBucket->where($column, $value);
        }
        $emptyBucket->where('transaction_count', 0)->delete();
    }

    private function dateKeyExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(CAST(strftime('%Y%m%d', created_at) AS INTEGER), 0)"
            : "COALESCE(CAST(DATE_FORMAT(created_at, '%Y%m%d') AS UNSIGNED), 0)";
    }
}
