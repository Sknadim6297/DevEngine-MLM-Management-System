<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$targetRows = (int) ($argv[1] ?? 100000);
$chunkSize = 5000;

DB::table('level_commission_transactions')->delete();

$startInsert = microtime(true);
$rows = [];
for ($n = 1; $n <= $targetRows; $n++) {
    $id = str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    $memberNumber = str_pad((string) (($n % 150) + 1), 4, '0', STR_PAD_LEFT);
    $date = sprintf('2026-%02d-%02d 12:00:00', 1 + (($n - 1) % 12), 1 + (($n - 1) % 28));
    $rows[] = [
        'reference' => 'LC-BENCH-' . $id,
        'investment_id' => 'INV-BENCH-' . $id,
        'member_id' => 'STBENCH' . $memberNumber,
        'member_name' => 'Benchmark Member ' . $memberNumber,
        'from_member_id' => 'STSOURCE' . $memberNumber,
        'from_member_name' => 'Source Member ' . $memberNumber,
        'level' => (($n % 32) + 1),
        'business_date' => substr($date, 0, 10),
        'on_amount' => number_format((float) ($n % 2000 + 100), 4, '.', ''),
        'rate_percentage' => number_format((float) (($n % 9) + 1) / 10, 3, '.', ''),
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
$insertTime = microtime(true) - $startInsert;

DB::listen(function ($query) use (&$queries): void {
    $queries[] = [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time' => $query->time,
    ];
});

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$memoryBefore = memory_get_usage(true);
$startRequest = microtime(true);
$response = $app['Illuminate\Contracts\Http\Kernel']->handle(Request::create('/admin/report/level-income', 'GET'));
$requestTime = microtime(true) - $startRequest;
$memoryAfter = memory_get_usage(true);

$summary = [
    'rows' => $targetRows,
    'insert_seconds' => round($insertTime, 3),
    'query_count' => count($queries ?? []),
    'request_seconds' => round($requestTime, 3),
    'memory_before' => $memoryBefore,
    'memory_after' => $memoryAfter,
    'response_status' => $response->getStatusCode(),
    'content_length' => strlen($response->getContent()),
    'slow_queries' => collect($queries ?? [])->sortByDesc('time')->take(5)->values()->all(),
];

$plan = DB::select('EXPLAIN SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions ORDER BY created_at DESC, id DESC LIMIT 50');

file_put_contents(__DIR__ . '/benchmark-output-' . $targetRows . '.json', json_encode([
    'summary' => $summary,
    'explain' => $plan,
], JSON_PRETTY_PRINT));

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
