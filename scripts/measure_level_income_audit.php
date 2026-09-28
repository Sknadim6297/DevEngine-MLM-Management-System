<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

DB::enableQueryLog();
DB::flushQueryLog();

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$start = microtime(true);
$request = Request::create('/admin/report/level-income', 'GET', ['page' => 1, 'per_page' => 50]);
$response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
$content = $response->getContent();
$app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
$elapsed = microtime(true) - $start;

$queries = DB::getQueryLog();
$summary = [
    'http_ms' => round($elapsed * 1000, 2),
    'query_count' => count($queries),
    'total_sql_ms' => round(array_sum(array_column($queries, 'time')), 2),
];

foreach ($queries as $i => $q) {
    $sql = preg_replace('/\s+/', ' ', $q['query']);
    echo ($i + 1) . ") " . $sql . " | bindings=" . json_encode($q['bindings']) . " | time=" . round($q['time'], 2) . "ms\n";
}

echo "\nSUMMARY\n";
foreach ($summary as $k => $v) {
    echo $k . " => " . $v . "\n";
}

echo "\nTOTAL_ROWS=" . DB::table('level_commission_transactions')->count() . "\n";
