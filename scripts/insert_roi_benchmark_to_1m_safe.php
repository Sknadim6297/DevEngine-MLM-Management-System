<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$targetTotal = 1_000_000;
$prefix = 'ROI-BENCH-';
$chunkSize = 2000;

$before = (int) DB::table('roi_transactions')->count();

if ($before >= $targetTotal) {
    echo "TARGET_ALREADY_REACHED rows_before={$before} target={$targetTotal}\n";
    exit(0);
}

$start = microtime(true);
$rows = [];
$inserted = 0;
$batchTimes = [];
$failedBatches = 0;
$sequenceStart = $before + 1;

for ($n = $sequenceStart; $n <= $targetTotal; $n++) {
    $id = str_pad((string) $n, 10, '0', STR_PAD_LEFT);
    $memberNumber = str_pad((string) (($n % 150) + 1), 4, '0', STR_PAD_LEFT);
    $date = sprintf('2026-%02d-%02d', 1 + (($n - 1) % 12), 1 + (($n - 1) % 28));
    $createdAt = $date . ' 12:00:00';

    $rows[] = [
        'reference' => $prefix . $id,
        'investment_id' => 'INV-ROI-BENCH-' . $id,
        'member_id' => 'STBENCH' . $memberNumber,
        'member_name' => 'Benchmark Member ' . $memberNumber,
        'on_amount' => number_format((float) ($n % 2000 + 100), 4, '.', ''),
        'rate_percentage' => number_format((float) (($n % 9) + 1) / 10, 3, '.', ''),
        'income_amount' => number_format((float) ($n % 1200 + 10), 4, '.', ''),
        'roi_date' => $date,
        'status' => 'generated',
        'withdrawable_on' => date('Y-m-d', strtotime($date . ' +1 month')),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ];

    if (count($rows) >= $chunkSize) {
        $batchStart = microtime(true);
        try {
            DB::table('roi_transactions')->insert($rows);
            $inserted += count($rows);
            $batchTimes[] = microtime(true) - $batchStart;
        } catch (Throwable $e) {
            $failedBatches++;
            fwrite(STDERR, "FAILED_BATCH batch_size=" . count($rows) . ' error=' . $e->getMessage() . PHP_EOL);
            throw $e;
        }

        $rows = [];

        if ($inserted % 100000 === 0 || $inserted >= ($targetTotal - $before)) {
            echo sprintf("progress rows_inserted=%d / %d elapsed_seconds=%.2f\n", $inserted, $targetTotal - $before, microtime(true) - $start);
        }
    }
}

if ($rows) {
    $batchStart = microtime(true);
    try {
        DB::table('roi_transactions')->insert($rows);
        $inserted += count($rows);
        $batchTimes[] = microtime(true) - $batchStart;
    } catch (Throwable $e) {
        $failedBatches++;
        fwrite(STDERR, "FAILED_BATCH batch_size=" . count($rows) . ' error=' . $e->getMessage() . PHP_EOL);
        throw $e;
    }
}

$after = (int) DB::table('roi_transactions')->count();
$elapsed = microtime(true) - $start;
$avgBatch = $batchTimes === [] ? 0 : array_sum($batchTimes) / count($batchTimes);

printf(
    "records_before=%d\nbenchmark_records_inserted=%d\nrecords_after=%d\ninsertion_time_seconds=%.2f\naverage_batch_time_seconds=%.4f\nbatch_size=%d\nfailed_batches=%d\n",
    $before,
    $inserted,
    $after,
    $elapsed,
    $avgBatch,
    $chunkSize,
    $failedBatches
);
