<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_commission_transactions', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'lct_created_at_id_index');
            $table->index(['level', 'created_at', 'id'], 'lct_level_created_at_id_index');
            $table->index('income_amount', 'lct_income_amount_index');
        });
    }

    public function down(): void
    {
        Schema::table('level_commission_transactions', function (Blueprint $table): void {
            $table->dropIndex('lct_created_at_id_index');
            $table->dropIndex('lct_level_created_at_id_index');
            $table->dropIndex('lct_income_amount_index');
        });
    }
};