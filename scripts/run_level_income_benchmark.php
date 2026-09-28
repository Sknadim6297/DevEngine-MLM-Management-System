<?php

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$targetRows = (int) ($argv[1] ?? 100000);
$chunkSize = 1000;
$outputFile = __DIR__ . '/level-income-benchmark-' . $targetRows . '.json';

DB::statement('SET foreign_key_checks = 0');
DB::table('level_commission_transactions')->delete();
DB::statement('SET foreign_key_checks = 1');

$insertStart = microtime(true);
$rows = [];
for ($n = 1; $n <= $targetRows; $n++) {
    $id = str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    $memberNumber = str_pad((string) (($n % 150) + 1), 4, '0', STR_PAD_LEFT);
    $createdAt = sprintf('2026-%02d-%02d 12:00:00', 1 + (($n - 1) % 12), 1 + (($n - 1) % 28));

    $rows[] = [
        'reference' => 'LC-BENCH-' . $id,
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
        DB::table('level_commission_transactions')->insert($rows);
        $rows = [];
    }
}
if ($rows) {
    DB::table('level_commission_transactions')->insert($rows);
}

$totalRows = DB::table('level_commission_transactions')->count();
$lastPage = max(1, (int) ceil($totalRows / 50));
$deepPage = min(20000, $lastPage);

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$cases = [
    'page_1' => ['page' => 1, 'per_page' => 50],
    'page_100' => ['page' => 100, 'per_page' => 50],
    'page_1000' => ['page' => 1000, 'per_page' => 50],
    'deep_page' => ['page' => $deepPage, 'per_page' => 50],
    'member_filter' => ['member_id' => 'STBENCH0002', 'page' => 1, 'per_page' => 50],
    'level_filter' => ['level' => 3, 'page' => 1, 'per_page' => 50],
    'date_filter' => ['from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'page' => 1, 'per_page' => 50],
    'combined_filter' => ['member_id' => 'STBENCH0002', 'level' => 2, 'from_date' => '2026-01-02', 'to_date' => '2026-01-31', 'page' => 1, 'per_page' => 50],
];

$results = [
    'dataset_rows' => $targetRows,
    'generated_at' => now()->toDateTimeString(),
    'insert_seconds' => round(microtime(true) - $insertStart, 3),
    'actual_rows' => $totalRows,
    'last_page' => $lastPage,
    'deep_page' => $deepPage,
    'cases' => [],
];

foreach ($cases as $name => $params) {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = [
            'sql' => $query->sql,
            'time_ms' => (float) $query->time,
            'bindings' => $query->bindings,
        ];
    });

    $beforeMemory = memory_get_usage(true);
    $timeStart = microtime(true);
    $request = Request::create('/admin/report/level-income', 'GET', $params);
    $response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
    $content = $response->getContent();
    $app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
    $elapsed = round(microtime(true) - $timeStart, 3);

    $results['cases'][$name] = [
        'status' => $response->getStatusCode(),
        'http_seconds' => $elapsed,
        'memory_delta_bytes' => memory_get_usage(true) - $beforeMemory,
        'query_count' => count($queries),
        'output_length' => strlen($content),
        'slowest_query_ms' => round(collect($queries)->max('time_ms') ?? 0, 3),
        'queries' => array_slice($queries, 0, 10),
    ];
}

$results['explain_default'] = DB::select("EXPLAIN SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions ORDER BY created_at DESC, id DESC LIMIT 50");
$results['explain_member'] = DB::select("EXPLAIN SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions WHERE member_id LIKE 'STBENCH0002%' ORDER BY created_at DESC, id DESC LIMIT 50");
$results['explain_level'] = DB::select("EXPLAIN SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions WHERE level = 3 ORDER BY created_at DESC, id DESC LIMIT 50");
$results['explain_date'] = DB::select("EXPLAIN SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions WHERE created_at >= '2026-01-01 00:00:00' AND created_at < '2026-02-01 00:00:00' ORDER BY created_at DESC, id DESC LIMIT 50");

file_put_contents($outputFile, json_encode($results, JSON_PRETTY_PRINT));

echo "Wrote: " . $outputFile . PHP_EOL;
