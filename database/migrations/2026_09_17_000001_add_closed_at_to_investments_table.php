<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->index(['status', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex(['status', 'closed_at']);
            $table->dropColumn('closed_at');
        });
    }
};
