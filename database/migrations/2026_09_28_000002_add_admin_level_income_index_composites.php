<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_commission_transactions', function (Blueprint $table): void {
            $table->index(['member_id', 'created_at', 'id'], 'lct_member_created_at_id_index');
            $table->index(['level', 'member_id', 'created_at', 'id'], 'lct_level_member_created_at_id_index');
            $table->index(['member_id', 'level', 'created_at', 'id'], 'lct_member_level_created_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('level_commission_transactions', function (Blueprint $table): void {
            $table->dropIndex('lct_member_created_at_id_index');
            $table->dropIndex('lct_level_member_created_at_id_index');
            $table->dropIndex('lct_member_level_created_at_id_index');
        });
    }
};
