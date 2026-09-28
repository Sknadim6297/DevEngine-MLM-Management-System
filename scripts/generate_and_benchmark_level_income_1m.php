<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$targetRows = 1_000_000;
$benchmarkPrefix = 'LC-BENCH-';
$existingBenchRows = DB::table('level_commission_transactions')
    ->where('reference', 'like', $benchmarkPrefix . '%')
    ->count();

if ($existingBenchRows >= $targetRows) {
    echo "Benchmark rows already present: {$existingBenchRows}\n";
    $startAt = $existingBenchRows;
} else {
    $startAt = $existingBenchRows;
    $chunkSize = 100;
    $rows = [];
    $startedAt = microtime(true);

    for ($n = $startAt + 1; $n <= $targetRows; $n++) {
        $id = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        $memberNumber = str_pad((string) (($n % 150) + 1), 4, '0', STR_PAD_LEFT);
        $createdAt = sprintf('2026-%02d-%02d 12:00:00', 1 + (($n - 1) % 12), 1 + (($n - 1) % 28));

        $rows[] = [
            'reference' => $benchmarkPrefix . $id,
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
            if ($n % 100000 === 0) {
                echo "Inserted {$n} benchmark rows...\n";
            }
        }
    }

    if ($rows) {
        DB::table('level_commission_transactions')->insert($rows);
    }

    echo "Generation complete in " . round(microtime(true) - $startedAt, 3) . " seconds\n";
}

$actualBenchmarkRows = DB::table('level_commission_transactions')
    ->where('reference', 'like', $benchmarkPrefix . '%')
    ->count();

echo "Benchmark rows present: {$actualBenchmarkRows}\n";

$lastPage = max(1, (int) ceil($actualBenchmarkRows / 50));
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
    'benchmark_rows' => $actualBenchmarkRows,
    'last_page' => $lastPage,
    'deep_page' => $deepPage,
    'cases' => [],
];

foreach ($cases as $name => $params) {
    $beforeMemory = memory_get_usage(true);
    $timeStart = microtime(true);
    $request = Request::create('/admin/report/level-income', 'GET', $params);
    $response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
    $content = $response->getContent();
    $app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
    $seconds = round(microtime(true) - $timeStart, 3);

    $results['cases'][$name] = [
        'status' => $response->getStatusCode(),
        'http_seconds' => $seconds,
        'memory_delta_bytes' => memory_get_usage(true) - $beforeMemory,
        'output_length' => strlen($content),
        'has_total_amount' => stripos($content, 'Total Amount') !== false,
        'contains_pagination' => stripos($content, 'Showing') !== false,
        'contains_rows' => stripos($content, 'Benchmark Member') !== false,
    ];

    echo sprintf("%s: %0.3fs | status=%s | mem=%d bytes\n", $name, $seconds, $response->getStatusCode(), memory_get_usage(true) - $beforeMemory);
}

file_put_contents(__DIR__ . '/level-income-1m-http-benchmark.json', json_encode($results, JSON_PRETTY_PRINT));

echo "Saved benchmark summary to " . __DIR__ . '/level-income-1m-http-benchmark.json' . "\n";
