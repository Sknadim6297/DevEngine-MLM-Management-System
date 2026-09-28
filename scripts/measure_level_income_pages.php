<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$cases = [
    'page_1' => ['page' => 1, 'per_page' => 50],
    'page_100' => ['page' => 100, 'per_page' => 50],
    'page_1000' => ['page' => 1000, 'per_page' => 50],
];

foreach ($cases as $name => $params) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $before = microtime(true);
    $request = Request::create('/admin/report/level-income', 'GET', $params);
    $response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
    $response->getContent();
    $app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
    $elapsed = round((microtime(true) - $before) * 1000, 2);
    $queries = DB::getQueryLog();
    $totalSql = round(array_sum(array_column($queries, 'time')), 2);
    echo sprintf("%s | http_ms=%s | query_count=%d | total_sql_ms=%s\n", $name, $elapsed, count($queries), $totalSql);
    foreach ($queries as $i => $query) {
        $sql = preg_replace('/\s+/', ' ', $query['query']);
        echo sprintf("  %d) %s | time_ms=%s\n", $i + 1, $sql, round($query['time'], 2));
    }
    echo "\n";
}
