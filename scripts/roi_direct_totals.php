<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

$cases = [
    'no_filter' => [],
    'member_filter' => ['member_id' => 'STBENCH0002'],
    'date_filter' => ['from_date' => '2026-01-01', 'to_date' => '2026-12-31'],
    'combined_filter' => ['member_id' => 'STBENCH0002', 'from_date' => '2026-01-01', 'to_date' => '2026-12-31'],
];

foreach ($cases as $name => $filters) {
    $query = DB::table('roi_transactions');

    if (isset($filters['member_id']) && trim((string) $filters['member_id']) !== '') {
        $query->where('member_id', 'like', trim((string) $filters['member_id']) . '%');
    }

    if (isset($filters['from_date'])) {
        $query->where('created_at', '>=', CarbonImmutable::parse($filters['from_date'])->startOfDay());
    }

    if (isset($filters['to_date'])) {
        $query->where('created_at', '<', CarbonImmutable::parse($filters['to_date'])->addDay()->startOfDay());
    }

    $aggregate = $query->selectRaw('SUM(income_amount) as total_amount, COUNT(*) as total_rows')->first();
    echo sprintf("%s total_amount=%s total_rows=%s\n", $name, (string) ($aggregate->total_amount ?? 0), (string) ($aggregate->total_rows ?? 0));
}
