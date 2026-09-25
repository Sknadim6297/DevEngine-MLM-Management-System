<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rank_achievements', function (Blueprint $table) {
            $table->index('achieved_at', 'rank_achievements_achieved_at_index');
            $table->index(['rank_id', 'achieved_at'], 'rank_achievements_rank_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('rank_achievements', function (Blueprint $table) {
            $table->dropIndex('rank_achievements_achieved_at_index');
            $table->dropIndex('rank_achievements_rank_date_index');
        });
    }
};