<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$queries = [];
DB::listen(function ($query) use (&$queries): void {
    $queries[] = [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time' => (float) $query->time,
    ];
});

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$before = microtime(true);
$request = Request::create('/admin/report/level-income', 'GET', ['page' => 1, 'per_page' => 50]);
$response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
$content = $response->getContent();
$app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
$elapsedMs = round((microtime(true) - $before) * 1000, 2);

$summary = [
    'http_ms' => $elapsedMs,
    'queries' => count($queries),
    'sql_ms' => round(array_sum(array_column($queries, 'time')), 2),
];

echo "HTTP_MS=" . $summary['http_ms'] . PHP_EOL;
echo "QUERY_COUNT=" . $summary['queries'] . PHP_EOL;
echo "SQL_MS=" . $summary['sql_ms'] . PHP_EOL;

echo "---- QUERIES ----" . PHP_EOL;
foreach ($queries as $index => $q) {
    echo ($index + 1) . ") " . preg_replace('/\s+/', ' ', $q['sql']) . "\n";
    echo "bindings=" . json_encode($q['bindings']) . "\n";
    echo "time_ms=" . round($q['time'], 2) . "\n\n";
}

$indexes = DB::select('SHOW INDEX FROM level_commission_transactions');
echo "---- INDEXES ----" . PHP_EOL;
foreach ($indexes as $index) {
    echo json_encode((array) $index) . PHP_EOL;
}

echo "---- EXPLAIN MAIN SELECT ----" . PHP_EOL;
foreach (DB::select("EXPLAIN SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions ORDER BY created_at DESC, id DESC LIMIT 50") as $row) {
    echo json_encode((array) $row) . PHP_EOL;
}

echo "---- EXPLAIN COUNT ----" . PHP_EOL;
foreach (DB::select("EXPLAIN SELECT COUNT(*) FROM level_commission_transactions") as $row) {
    echo json_encode((array) $row) . PHP_EOL;
}
