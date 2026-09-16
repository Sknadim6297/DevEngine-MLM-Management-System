<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->decimal('percentage', 8, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'level']);
        });

        $now = now();
        $rows = [];

        foreach (range(1, 32) as $level) {
            $percentage = match (true) {
                $level === 1 => '1.0000',
                $level === 2 || $level === 3 => '0.5000',
                $level === 4 => '0.3000',
                $level <= 20 => '0.2500',
                default => '0.2000',
            };

            $rows[] = [
                'level' => $level,
                'percentage' => $percentage,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('level_commissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('level_commissions');
    }
};
