<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('unlocked_levels');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn('is_active');
        });
    }
};
