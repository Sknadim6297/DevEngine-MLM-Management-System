<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('member_name');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('category');
        });
    }
};
