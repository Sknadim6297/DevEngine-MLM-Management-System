<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('investments', 'category')) {
            Schema::table('investments', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('investments', 'category')) {
            Schema::table('investments', function (Blueprint $table) {
                $table->string('category', 20)->nullable()->after('member_name');
            });
        }
    }
};