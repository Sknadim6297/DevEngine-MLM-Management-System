<?php

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pageSize = 50;
$targetPage = (int) ($argv[1] ?? 5900);
$offset = max(0, ($targetPage - 1) * $pageSize);

$user = User::factory()->create(['is_admin' => true]);
Auth::login($user);

$query = DB::table('level_commission_transactions')
    ->select(['id', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'on_amount', 'created_at'])
    ->orderByDesc('created_at')
    ->orderByDesc('id');

$sql = $query->limit($pageSize)->offset($offset)->toSql();
$rawSql = sprintf(
    'SELECT id, member_id, member_name, from_member_id, level, income_amount, on_amount, created_at FROM level_commission_transactions ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
    $pageSize,
    $offset
);

$start = microtime(true);
$rows = $query->offset($offset)->limit($pageSize)->get();
$elapsed = microtime(true) - $start;

$explain = DB::select('EXPLAIN ' . $rawSql);
$explainAnalyze = DB::select('EXPLAIN ANALYZE ' . $rawSql);

$queries = [];
DB::listen(function ($queryEvent) use (&$queries): void {
    $queries[] = [
        'sql' => $queryEvent->sql,
        'time_ms' => (float) $queryEvent->time,
    ];
});

$reqStart = microtime(true);
$request = Request::create('/admin/report/level-income', 'GET', ['page' => $targetPage, 'per_page' => $pageSize]);
$response = $app['Illuminate\\Contracts\\Http\\Kernel']->handle($request);
$rendered = $response->getContent();
$app['Illuminate\\Contracts\\Http\\Kernel']->terminate($request, $response);
$reqElapsed = microtime(true) - $reqStart;

fwrite(STDOUT, "page=" . $targetPage . " offset=" . $offset . " page_size=" . $pageSize . PHP_EOL);

fwrite(STDOUT, "SQL:\n" . $rawSql . PHP_EOL . PHP_EOL);
fwrite(STDOUT, "fetch_seconds=" . round($elapsed, 6) . PHP_EOL);
fwrite(STDOUT, "request_seconds=" . round($reqElapsed, 6) . PHP_EOL);
fwrite(STDOUT, "rows_returned=" . $rows->count() . PHP_EOL);

fwrite(STDOUT, "EXPLAIN:\n");
foreach ($explain as $row) {
    fwrite(STDOUT, json_encode((array) $row, JSON_PRETTY_PRINT) . PHP_EOL);
}

fwrite(STDOUT, "EXPLAIN ANALYZE:\n");
foreach ($explainAnalyze as $row) {
    fwrite(STDOUT, json_encode((array) $row, JSON_PRETTY_PRINT) . PHP_EOL);
}

fwrite(STDOUT, "QUERY_LISTENER:\n");
foreach ($queries as $queryEntry) {
    fwrite(STDOUT, json_encode($queryEntry, JSON_PRETTY_PRINT) . PHP_EOL);
}

fwrite(STDOUT, "HTTP_STATUS=" . $response->getStatusCode() . PHP_EOL);
