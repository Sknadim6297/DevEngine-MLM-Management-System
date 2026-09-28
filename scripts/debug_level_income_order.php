<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::table('level_commission_transactions')->delete();
$rows = [];
for ($number = 1; $number <= 125; $number++) {
    $id = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    $memberNumber = str_pad((string) (($number % 10) + 1), 4, '0', STR_PAD_LEFT);
    $createdAt = sprintf('2026-01-%02d 12:00:00', (($number - 1) % 6) + 1);
    $rows[] = [
        'reference' => 'LC-BENCH-' . $id,
        'investment_id' => 'INV-BENCH-' . $id,
        'member_id' => 'STBENCH' . $memberNumber,
        'member_name' => 'Benchmark Member ' . $memberNumber,
        'from_member_id' => 'STSOURCE' . $id,
        'from_member_name' => 'Source Member ' . $id,
        'level' => ($number % 3) + 1,
        'business_date' => substr($createdAt, 0, 10),
        'on_amount' => '100.0000',
        'rate_percentage' => '2.000',
        'income_amount' => '1.0000',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ];
}
DB::table('level_commission_transactions')->insert($rows);

$latest = DB::table('level_commission_transactions')->orderByDesc('created_at')->orderByDesc('id')->value('reference');
$top = DB::table('level_commission_transactions')->orderByDesc('created_at')->orderByDesc('id')->limit(10)->pluck('reference')->all();

echo 'LATEST=' . $latest . PHP_EOL;
var_export($top);
