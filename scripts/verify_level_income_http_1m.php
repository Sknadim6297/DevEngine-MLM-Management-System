<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$benchmarkRows = DB::table('level_commission_transactions')->where('reference', 'like', 'LC-BENCH-%')->count();
$lastPage = max(1, (int) ceil($benchmarkRows / 50));
$deepPage = min(20000, $lastPage);

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$cases = [
    'page_1' => ['page' => 1, 'per_page' => 50],
    'page_100' => ['page' => 100, 'per_page' => 50],
    'page_1000' => ['page' => 1000, 'per_page' => 50],
    'deep_page' => ['page' => $deepPage, 'per_page' => 50],
    'last_page' => ['page' => $lastPage, 'per_page' => 50],
    'member_filter' => ['member_id' => 'STBENCH0002', 'page' => 1, 'per_page' => 50],
    'level_filter' => ['level' => 3, 'page' => 1, 'per_page' => 50],
    'date_filter' => ['from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'page' => 1, 'per_page' => 50],
    'combined_filter' => ['member_id' => 'STBENCH0002', 'level' => 2, 'from_date' => '2026-01-02', 'to_date' => '2026-01-31', 'page' => 1, 'per_page' => 50],
];

$results = [
    'benchmark_rows' => $benchmarkRows,
    'last_page' => $lastPage,
    'deep_page' => $deepPage,
    'cases' => [],
];

foreach ($cases as $name => $params) {
    $beforeMemory = memory_get_usage(true);
    $start = microtime(true);
    $request = Request::create('/admin/report/level-income', 'GET', $params);
    $response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
    $content = $response->getContent();
    $app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
    $elapsed = round(microtime(true) - $start, 3);

    $results['cases'][$name] = [
        'status' => $response->getStatusCode(),
        'http_seconds' => $elapsed,
        'memory_delta_bytes' => memory_get_usage(true) - $beforeMemory,
        'output_length' => strlen($content),
        'has_total_amount' => stripos($content, 'Total Amount') !== false,
        'contains_rows' => stripos($content, 'Benchmark Member') !== false,
        'contains_pagination' => stripos($content, 'Showing') !== false,
    ];

    echo sprintf("%s: %.3fs | status=%s | mem=%d bytes | total=%s | rows=%s\n",
        $name,
        $elapsed,
        $response->getStatusCode(),
        memory_get_usage(true) - $beforeMemory,
        $results['cases'][$name]['has_total_amount'] ? 'yes' : 'no',
        $results['cases'][$name]['contains_rows'] ? 'yes' : 'no'
    );
}

file_put_contents(__DIR__ . '/level-income-1m-http-benchmark.json', json_encode($results, JSON_PRETTY_PRINT));

echo "Saved summary to " . __DIR__ . '/level-income-1m-http-benchmark.json' . PHP_EOL;
