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
    'deep_page' => ['page' => $deepPage, 'per_page' => 50],
    'last_page' => ['page' => $lastPage, 'per_page' => 50],
];

foreach ($cases as $name => $params) {
    $before = memory_get_usage(true);
    $t = microtime(true);
    $request = Request::create('/admin/report/level-income', 'GET', $params);
    $response = $app['Illuminate\Contracts\Http\Kernel']->handle($request);
    $content = $response->getContent();
    $app['Illuminate\Contracts\Http\Kernel']->terminate($request, $response);
    $elapsed = round(microtime(true) - $t, 3);
    $rows = stripos($content, 'Benchmark Member') !== false ? 'yes' : 'no';
    $total = stripos($content, 'Total Amount') !== false ? 'yes' : 'no';
    echo sprintf("%s: %.3fs | status=%s | mem=%d | total=%s | rows=%s\n", $name, $elapsed, $response->getStatusCode(), memory_get_usage(true) - $before, $total, $rows);
}
