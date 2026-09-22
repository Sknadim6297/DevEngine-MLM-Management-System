<?php

namespace Database\Seeders;

use App\Console\Commands\SeedDemoData;
use Illuminate\Database\Seeder;

class DemoDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $command = app(SeedDemoData::class);
        $command->seedDataset(now()->setTimezone('Asia/Kolkata')->startOfDay());
    }
}
