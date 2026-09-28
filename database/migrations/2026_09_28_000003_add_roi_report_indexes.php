<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roi_transactions', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'roi_created_at_id_index');
            $table->index(['member_id', 'created_at', 'id'], 'roi_member_created_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('roi_transactions', function (Blueprint $table): void {
            $table->dropIndex('roi_created_at_id_index');
            $table->dropIndex('roi_member_created_at_id_index');
        });
    }
};
