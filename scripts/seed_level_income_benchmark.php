<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$targetRows = (int) ($argv[1] ?? 100000);
$chunkSize = 5000;
$start = microtime(true);

DB::statement('SET foreign_key_checks = 0');
DB::table('level_commission_transactions')->delete();
DB::statement('SET foreign_key_checks = 1');

$rows = [];
for ($n = 1; $n <= $targetRows; $n++) {
    $id = str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    $memberNumber = str_pad((string) (($n % 150) + 1), 4, '0', STR_PAD_LEFT);
    $date = sprintf('2026-%02d-%02d 12:00:00', 1 + (($n - 1) % 12), 1 + (($n - 1) % 28));

    $rows[] = [
        'reference' => 'LC-LOAD-' . $id,
        'investment_id' => 'INV-LOAD-' . $id,
        'member_id' => 'M' . $memberNumber,
        'member_name' => 'Member ' . $memberNumber,
        'from_member_id' => 'SRC' . $memberNumber,
        'from_member_name' => 'Source ' . $memberNumber,
        'level' => (($n % 32) + 1),
        'business_date' => substr($date, 0, 10),
        'on_amount' => number_format((float) ($n % 2000 + 100), 4, '.', ''),
        'rate_percentage' => number_format((float) ($n % 9 + 1) / 10, 3, '.', ''),
        'income_amount' => number_format((float) ($n % 1200 + 10), 4, '.', ''),
        'created_at' => $date,
        'updated_at' => $date,
    ];

    if (count($rows) >= $chunkSize) {
        DB::table('level_commission_transactions')->insert($rows);
        $rows = [];
    }
}

if ($rows) {
    DB::table('level_commission_transactions')->insert($rows);
}

$count = DB::table('level_commission_transactions')->count();
$elapsed = microtime(true) - $start;

echo "Inserted {$count} level commission rows in {$elapsed} seconds." . PHP_EOL;
