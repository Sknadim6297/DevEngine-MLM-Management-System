<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$targetTotal = 2_000_000;
$prefix = 'LC-BENCH-';
$chunkSize = 2000;

$before = (int) DB::table('level_commission_transactions')
    ->where('reference', 'like', $prefix . '%')
    ->count();

if ($before >= $targetTotal) {
    echo "TARGET_ALREADY_REACHED rows_before={$before} target={$targetTotal}\n";
    exit(0);
}

$start = microtime(true);
$rows = [];
$inserted = 0;
$batchTimes = [];

for ($n = $before + 1; $n <= $targetTotal; $n++) {
    $id = str_pad((string) $n, 7, '0', STR_PAD_LEFT);
    $memberNumber = str_pad((string) (($n % 150) + 1), 4, '0', STR_PAD_LEFT);
    $createdAt = sprintf('2026-%02d-%02d 12:00:00', 1 + (($n - 1) % 12), 1 + (($n - 1) % 28));

    $rows[] = [
        'reference' => $prefix . $id,
        'investment_id' => 'INV-BENCH-' . $id,
        'member_id' => 'STBENCH' . $memberNumber,
        'member_name' => 'Benchmark Member ' . $memberNumber,
        'from_member_id' => 'STSOURCE' . $memberNumber,
        'from_member_name' => 'Source Member ' . $memberNumber,
        'level' => (($n % 32) + 1),
        'business_date' => substr($createdAt, 0, 10),
        'on_amount' => number_format((float) ($n % 2000 + 100), 4, '.', ''),
        'rate_percentage' => number_format((float) (($n % 9) + 1) / 10, 3, '.', ''),
        'income_amount' => number_format((float) ($n % 1200 + 10), 4, '.', ''),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ];

    if (count($rows) >= $chunkSize) {
        $batchStart = microtime(true);
        DB::table('level_commission_transactions')->insert($rows);
        $batchTimes[] = microtime(true) - $batchStart;
        $inserted += count($rows);
        $rows = [];

        if ($inserted % 100000 === 0 || $inserted >= ($targetTotal - $before)) {
            echo sprintf("progress rows_inserted=%d / %d elapsed_seconds=%.2f\n", $inserted, $targetTotal - $before, microtime(true) - $start);
        }
    }
}

if ($rows) {
    $batchStart = microtime(true);
    DB::table('level_commission_transactions')->insert($rows);
    $batchTimes[] = microtime(true) - $batchStart;
    $inserted += count($rows);
}

$after = (int) DB::table('level_commission_transactions')
    ->where('reference', 'like', $prefix . '%')
    ->count();

$elapsed = microtime(true) - $start;
$avgBatch = $batchTimes === [] ? 0 : array_sum($batchTimes) / count($batchTimes);

printf(
    "rows_before=%d\nrows_inserted=%d\nrows_after=%d\ntotal_insertion_time_seconds=%.2f\naverage_batch_time_seconds=%.4f\n",
    $before,
    $inserted,
    $after,
    $elapsed,
    $avgBatch
);
