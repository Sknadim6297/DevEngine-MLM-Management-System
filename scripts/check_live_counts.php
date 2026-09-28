<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$total = DB::table('level_commission_transactions')->count();
$bench = DB::table('level_commission_transactions')->where('reference', 'like', 'LC-BENCH-%')->count();
$roi = DB::table('roi_transactions')->count();

echo "level_commission_transactions={$total}\n";
echo "benchmark_rows={$bench}\n";
echo "roi_transactions={$roi}\n";
