<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RebuildReportSummaries extends Command
{
    protected $signature = 'reports:rebuild-summaries {--chunk=20000 : Source transaction ID batch size} {--restart : Discard an incomplete generation and start over}';

    protected $description = 'Build, validate, and activate exact daily ROI and Level Commission report summaries.';

    private const LOCK_NAME = 'devengine_report_summary_rebuild';

    private const ROI_ROLLUPS = [
        [
            'table' => 'roi_report_member_daily_summaries',
            'columns' => '`member_id`, `date_key`',
            'source' => '`member_id`, COALESCE(CAST(DATE_FORMAT(`created_at`, \'%Y%m%d\') AS UNSIGNED), 0)',
            'group_by' => '`member_id`, COALESCE(CAST(DATE_FORMAT(`created_at`, \'%Y%m%d\') AS UNSIGNED), 0)',
        ],
    ];

    private const LEVEL_ROLLUPS = [
        [
            'table' => 'level_commission_report_member_level_daily_summaries',
            'columns' => '`member_id`, `level`, `date_key`',
            'source' => '`member_id`, `level`, COALESCE(CAST(DATE_FORMAT(`created_at`, \'%Y%m%d\') AS UNSIGNED), 0)',
            'group_by' => '`member_id`, `level`, COALESCE(CAST(DATE_FORMAT(`created_at`, \'%Y%m%d\') AS UNSIGNED), 0)',
        ],
    ];

    public function handle(): int
    {
        $chunkSize = max(1000, min(100000, (int) $this->option('chunk')));
        $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_NAME]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            $this->error('Another report summary rebuild is already running.');

            return self::FAILURE;
        }

        $generationId = null;
        try {
            $generationId = $this->prepareGeneration();
            $this->info("Building report summary generation {$generationId} in batches of {$chunkSize} source IDs.");
            $this->processTable($generationId, 'roi_transactions', 'roi_last_transaction_id', 'roi_source_max_id', self::ROI_ROLLUPS, $chunkSize);
            $this->processTable($generationId, 'level_commission_transactions', 'level_last_transaction_id', 'level_source_max_id', self::LEVEL_ROLLUPS, $chunkSize);
            $this->deriveRollups($generationId);

            $validation = $this->validateGeneration($generationId);
            if ($validation['mismatches'] !== []) {
                throw new \RuntimeException('Summary validation found mismatches: ' . json_encode($validation['mismatches']));
            }

            $this->activateGeneration($generationId);
            $this->printValidation($validation);
            $this->info("Activated report summary generation {$generationId}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($generationId !== null) {
                $this->markFailed($generationId, $exception->getMessage());
            }

            $this->error('Report summary rebuild failed: ' . $exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
        }
    }

    private function prepareGeneration(): int
    {
        return DB::transaction(function (): int {
            $state = DB::table('report_summary_state')->where('id', 1)->lockForUpdate()->first();
            if (! $state) {
                throw new \RuntimeException('Report summary state is missing.');
            }

            $generation = DB::table('report_summary_generations')
                ->whereIn('status', ['building', 'failed'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($generation && $this->option('restart')) {
                if ((int) $state->active_generation_id === (int) $generation->id) {
                    throw new \RuntimeException('Cannot restart the currently active summary generation.');
                }

                DB::table('report_summary_generations')->where('id', $generation->id)->delete();
                $generation = null;
            }

            $timezone = DB::selectOne('SELECT @@session.time_zone AS session_timezone, @@system_time_zone AS system_timezone');
            $timezoneName = ($timezone->session_timezone ?? 'UNKNOWN') . '/' . ($timezone->system_timezone ?? 'UNKNOWN');
            $roiMaxId = (int) DB::table('roi_transactions')->max('id');
            $levelMaxId = (int) DB::table('level_commission_transactions')->max('id');

            if ($generation) {
                DB::table('report_summary_generations')->where('id', $generation->id)->update([
                    'status' => 'building',
                    'roi_source_max_id' => max((int) $generation->roi_source_max_id, $roiMaxId),
                    'level_source_max_id' => max((int) $generation->level_source_max_id, $levelMaxId),
                    'source_timezone' => $timezoneName,
                    'failure_message' => null,
                    'updated_at' => now(),
                ]);

                $generationId = (int) $generation->id;
            } else {
                $generationId = (int) DB::table('report_summary_generations')->insertGetId([
                    'status' => 'building',
                    'roi_last_transaction_id' => 0,
                    'level_last_transaction_id' => 0,
                    'roi_source_max_id' => $roiMaxId,
                    'level_source_max_id' => $levelMaxId,
                    'source_timezone' => $timezoneName,
                    'failure_message' => null,
                    'validated_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('report_summary_state')->where('id', 1)->update([
                'rebuild_in_progress' => true,
                'updated_at' => now(),
            ]);

            return $generationId;
        }, 3);
    }

    private function processTable(
        int $generationId,
        string $sourceTable,
        string $cursorColumn,
        string $maxColumn,
        array $rollups,
        int $chunkSize
    ): void {
        while (true) {
            $generation = DB::table('report_summary_generations')->where('id', $generationId)->first();
            $cursor = (int) $generation->{$cursorColumn};
            $sourceMax = (int) $generation->{$maxColumn};
            if ($cursor >= $sourceMax) {
                return;
            }

            $ids = DB::table($sourceTable)
                ->where('id', '>', $cursor)
                ->where('id', '<=', $sourceMax)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            $upperId = (int) ($ids->last() ?? $sourceMax);
            if ($upperId <= $cursor) {
                $upperId = $sourceMax;
            }

            DB::transaction(function () use ($generationId, $sourceTable, $cursorColumn, $cursor, $upperId, $rollups): void {
                foreach ($rollups as $rollup) {
                    $this->upsertSourceRange($sourceTable, $rollup, $generationId, $cursor, $upperId);
                }

                DB::table('report_summary_generations')->where('id', $generationId)->update([
                    $cursorColumn => $upperId,
                    'updated_at' => now(),
                ]);
            }, 3);

            $this->line(sprintf('%s: checkpoint %d', $sourceTable, $upperId));
        }
    }

    private function upsertSourceRange(string $sourceTable, array $rollup, int $generationId, int $lowerId, int $upperId): void
    {
        $dimensionColumns = $rollup['columns'];
        $sourceDimensions = $rollup['source'];
        $groupBy = $rollup['group_by'];
        $sql = 'INSERT INTO `' . $rollup['table'] . '` (`generation_id`, ' . $dimensionColumns . ', `transaction_count`, `total_income_amount`) '
            . 'SELECT ?, ' . $sourceDimensions . ', COUNT(*), SUM(`income_amount`) FROM `' . $sourceTable . '` '
            . 'WHERE `id` > ? AND `id` <= ? GROUP BY ' . $groupBy . ' '
            . 'ON DUPLICATE KEY UPDATE `transaction_count` = `transaction_count` + VALUES(`transaction_count`), '
            . '`total_income_amount` = `total_income_amount` + VALUES(`total_income_amount`)';

        DB::statement($sql, [$generationId, $lowerId, $upperId]);
    }

    private function validateGeneration(int $generationId): array
    {
        $specs = [
            ['roi_transactions', 'roi_report_member_daily_summaries', ['member_id', 'date_key']],
            ['level_commission_transactions', 'level_commission_report_member_level_daily_summaries', ['member_id', 'level', 'date_key']],
        ];

        $mismatches = [];
        foreach ($specs as [$sourceTable, $summaryTable, $dimensions]) {
            $mismatchCount = $this->groupMismatchCount($sourceTable, $summaryTable, $dimensions, $generationId);
            if ($mismatchCount !== 0) {
                $mismatches[$summaryTable] = $mismatchCount;
            }
        }

        foreach ([
            ['roi_report_member_daily_summaries', 'roi_report_global_daily_summaries', ['date_key']],
            ['level_commission_report_member_level_daily_summaries', 'level_commission_report_level_daily_summaries', ['level', 'date_key']],
            ['level_commission_report_member_level_daily_summaries', 'level_commission_report_global_daily_summaries', ['date_key']],
        ] as [$sourceTable, $summaryTable, $dimensions]) {
            $mismatchCount = $this->summaryRollupMismatchCount($sourceTable, $summaryTable, $dimensions, $generationId);
            if ($mismatchCount !== 0) {
                $mismatches[$summaryTable] = $mismatchCount;
            }
        }

        return [
            'mismatches' => $mismatches,
            'roi_raw' => $this->rawTotals('roi_transactions'),
            'roi_summary' => $this->summaryTotals('roi_report_global_daily_summaries', $generationId),
            'level_raw' => $this->rawTotals('level_commission_transactions'),
            'level_summary' => $this->summaryTotals('level_commission_report_global_daily_summaries', $generationId),
        ];
    }

    private function groupMismatchCount(string $sourceTable, string $summaryTable, array $dimensions, int $generationId): int
    {
        $selectDimensions = [];
        $groupDimensions = [];
        foreach ($dimensions as $dimension) {
            if ($dimension === 'date_key') {
                $expression = "COALESCE(CAST(DATE_FORMAT(`created_at`, '%Y%m%d') AS UNSIGNED), 0)";
                $selectDimensions[] = $expression . ' AS `date_key`';
                $groupDimensions[] = $expression;
            } else {
                $selectDimensions[] = '`' . $dimension . '`';
                $groupDimensions[] = '`' . $dimension . '`';
            }
        }

        $dimensionSql = implode(', ', $selectDimensions);
        $groupSql = implode(', ', $groupDimensions);
        $joinSql = implode(' AND ', array_map(fn (string $dimension): string => 's.`' . $dimension . '` = r.`' . $dimension . '`', $dimensions));
        $rawGroups = '(SELECT ' . $dimensionSql . ', COUNT(*) AS `transaction_count`, SUM(`income_amount`) AS `total_income_amount` '
            . 'FROM `' . $sourceTable . '` GROUP BY ' . $groupSql . ')';

        $rawCompare = 'SELECT COUNT(*) AS `raw_group_count`, '
            . 'SUM(CASE WHEN s.`id` IS NULL OR s.`transaction_count` <> r.`transaction_count` OR s.`total_income_amount` <> r.`total_income_amount` THEN 1 ELSE 0 END) AS `mismatch_count` '
            . 'FROM ' . $rawGroups . ' r '
            . 'LEFT JOIN `' . $summaryTable . '` s ON s.`generation_id` = ? AND ' . $joinSql . ' '
            ;
        $rawResult = DB::selectOne($rawCompare, [$generationId]);
        $summaryCount = (int) DB::table($summaryTable)->where('generation_id', $generationId)->count();
        $rawGroupCount = (int) $rawResult->raw_group_count;

        return (int) ($rawResult->mismatch_count ?? 0) + ($rawGroupCount === $summaryCount ? 0 : 1);
    }

    private function deriveRollups(int $generationId): void
    {
        DB::transaction(function () use ($generationId): void {
            foreach ([
                'roi_report_global_daily_summaries',
                'level_commission_report_global_daily_summaries',
                'level_commission_report_level_daily_summaries',
            ] as $table) {
                DB::table($table)->where('generation_id', $generationId)->delete();
            }

            DB::statement(
                'INSERT INTO `roi_report_global_daily_summaries` (`generation_id`, `date_key`, `transaction_count`, `total_income_amount`) '
                . 'SELECT `generation_id`, `date_key`, SUM(`transaction_count`), SUM(`total_income_amount`) '
                . 'FROM `roi_report_member_daily_summaries` WHERE `generation_id` = ? GROUP BY `generation_id`, `date_key`',
                [$generationId]
            );

            DB::statement(
                'INSERT INTO `level_commission_report_level_daily_summaries` (`generation_id`, `level`, `date_key`, `transaction_count`, `total_income_amount`) '
                . 'SELECT `generation_id`, `level`, `date_key`, SUM(`transaction_count`), SUM(`total_income_amount`) '
                . 'FROM `level_commission_report_member_level_daily_summaries` WHERE `generation_id` = ? GROUP BY `generation_id`, `level`, `date_key`',
                [$generationId]
            );

            DB::statement(
                'INSERT INTO `level_commission_report_global_daily_summaries` (`generation_id`, `date_key`, `transaction_count`, `total_income_amount`) '
                . 'SELECT `generation_id`, `date_key`, SUM(`transaction_count`), SUM(`total_income_amount`) '
                . 'FROM `level_commission_report_member_level_daily_summaries` WHERE `generation_id` = ? GROUP BY `generation_id`, `date_key`',
                [$generationId]
            );
        }, 3);
    }

    private function summaryRollupMismatchCount(string $sourceTable, string $summaryTable, array $dimensions, int $generationId): int
    {
        $selectDimensions = implode(', ', array_map(fn (string $dimension): string => '`' . $dimension . '`', $dimensions));
        $groupDimensions = $selectDimensions;
        $joinSql = implode(' AND ', array_map(fn (string $dimension): string => 's.`' . $dimension . '` = r.`' . $dimension . '`', $dimensions));
        $sourceGroups = '(SELECT ' . $selectDimensions . ', SUM(`transaction_count`) AS `transaction_count`, '
            . 'SUM(`total_income_amount`) AS `total_income_amount` FROM `' . $sourceTable . '` '
            . 'WHERE `generation_id` = ? GROUP BY ' . $groupDimensions . ')';
        $sql = 'SELECT COUNT(*) AS `source_group_count`, '
            . 'SUM(CASE WHEN s.`id` IS NULL OR s.`transaction_count` <> r.`transaction_count` '
            . 'OR s.`total_income_amount` <> r.`total_income_amount` THEN 1 ELSE 0 END) AS `mismatch_count` '
            . 'FROM ' . $sourceGroups . ' r LEFT JOIN `' . $summaryTable . '` s '
            . 'ON s.`generation_id` = ? AND ' . $joinSql;
        $result = DB::selectOne($sql, [$generationId, $generationId]);
        $summaryCount = (int) DB::table($summaryTable)->where('generation_id', $generationId)->count();

        return (int) ($result->mismatch_count ?? 0) + ((int) $result->source_group_count === $summaryCount ? 0 : 1);
    }

    private function rawTotals(string $table): array
    {
        $row = DB::table($table)
            ->selectRaw('COUNT(*) AS transaction_count, COALESCE(SUM(income_amount), 0) AS total_income_amount')
            ->first();

        return ['count' => (string) $row->transaction_count, 'sum' => (string) $row->total_income_amount];
    }

    private function summaryTotals(string $table, int $generationId): array
    {
        $row = DB::table($table)
            ->where('generation_id', $generationId)
            ->selectRaw('COALESCE(SUM(transaction_count), 0) AS transaction_count, COALESCE(SUM(total_income_amount), 0) AS total_income_amount')
            ->first();

        return ['count' => (string) $row->transaction_count, 'sum' => (string) $row->total_income_amount];
    }

    private function activateGeneration(int $generationId): void
    {
        DB::transaction(function () use ($generationId): void {
            $state = DB::table('report_summary_state')->where('id', 1)->lockForUpdate()->first();
            $previousGenerationId = $state->active_generation_id;

            DB::table('report_summary_generations')->where('id', $generationId)->update([
                'status' => 'active',
                'validated_at' => now(),
                'failure_message' => null,
                'updated_at' => now(),
            ]);

            if ($previousGenerationId !== null && (int) $previousGenerationId !== $generationId) {
                DB::table('report_summary_generations')->where('id', $previousGenerationId)->update([
                    'status' => 'retired',
                    'updated_at' => now(),
                ]);
            }

            DB::table('report_summary_state')->where('id', 1)->update([
                'active_generation_id' => $generationId,
                'writes_enabled' => true,
                'rebuild_in_progress' => false,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function markFailed(int $generationId, string $message): void
    {
        DB::transaction(function () use ($generationId, $message): void {
            DB::table('report_summary_generations')->where('id', $generationId)->update([
                'status' => 'failed',
                'failure_message' => mb_substr($message, 0, 60000),
                'updated_at' => now(),
            ]);

            $activeGenerationId = DB::table('report_summary_state')->where('id', 1)->value('active_generation_id');
            DB::table('report_summary_state')->where('id', 1)->update([
                'rebuild_in_progress' => false,
                'writes_enabled' => $activeGenerationId !== null,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function printValidation(array $validation): void
    {
        $this->table(['Source', 'Raw count', 'Summary count', 'Raw sum', 'Summary sum'], [
            ['ROI', $validation['roi_raw']['count'], $validation['roi_summary']['count'], $validation['roi_raw']['sum'], $validation['roi_summary']['sum']],
            ['Level Commission', $validation['level_raw']['count'], $validation['level_summary']['count'], $validation['level_raw']['sum'], $validation['level_summary']['sum']],
        ]);

        $this->info('All date/member/level rollup groups matched raw transactions exactly.');
    }
}
