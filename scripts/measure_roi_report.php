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
    'page_1' => ['page' => 1, 'per_page' => 10],
    'page_100' => ['page' => 100, 'per_page' => 10],
    'page_1000' => ['page' => 1000, 'per_page' => 10],
    'member_filter' => ['member_id' => 'STBENCH0002', 'page' => 1, 'per_page' => 10],
    'from_to_date' => ['from_date' => '2026-01-01', 'to_date' => '2026-12-31', 'page' => 1, 'per_page' => 10],
    'combined_filter' => ['member_id' => 'STBENCH0002', 'from_date' => '2026-01-01', 'to_date' => '2026-12-31', 'page' => 1, 'per_page' => 10],
];

foreach ($cases as $name => $params) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $before = microtime(true);
    $request = Request::create('/admin/report/roi-report', 'GET', $params);
    $response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
    $content = $response->getContent();
    $app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
    $elapsed = round((microtime(true) - $before) * 1000, 2);
    $queries = DB::getQueryLog();
    $totalSql = round(array_sum(array_column($queries, 'time')), 2);
    $slowest = collect($queries)->sortByDesc(fn ($q) => $q['time'])->first();
    $rowCount = preg_match_all('/<tr>/', $content) ?: 0;
    echo sprintf("%s | http_ms=%s | query_count=%d | total_sql_ms=%s | status=%s | rowCount=%d | slowest_ms=%s\n",
        $name,
        $elapsed,
        count($queries),
        $totalSql,
        $response->getStatusCode(),
        $rowCount,
        $slowest ? round($slowest['time'], 2) : 0
    );
    foreach ($queries as $i => $query) {
        $sql = preg_replace('/\s+/', ' ', $query['query']);
        echo sprintf("  %d) %s | time_ms=%s\n", $i + 1, $sql, round($query['time'], 2));
    }
    echo "\n";
}
