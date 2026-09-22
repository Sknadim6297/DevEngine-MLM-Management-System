<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('required_full_team_business', 20, 4);
            $table->unsignedTinyInteger('unlocked_levels');
            $table->unsignedTinyInteger('sort_order')->unique();
            $table->timestamps();

            $table->index('required_full_team_business');
        });

        $now = now();
        DB::table('ranks')->insert([
            ['name' => 'Silver', 'required_full_team_business' => '6000.0000', 'unlocked_levels' => 4, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gold', 'required_full_team_business' => '12000.0000', 'unlocked_levels' => 8, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Platinum', 'required_full_team_business' => '25000.0000', 'unlocked_levels' => 12, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ruby', 'required_full_team_business' => '50000.0000', 'unlocked_levels' => 16, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ruby Club', 'required_full_team_business' => '100000.0000', 'unlocked_levels' => 20, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Diamond', 'required_full_team_business' => '250000.0000', 'unlocked_levels' => 24, 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Blue Diamond', 'required_full_team_business' => '600000.0000', 'unlocked_levels' => 28, 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Master Blaster', 'required_full_team_business' => '1500000.0000', 'unlocked_levels' => 32, 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
