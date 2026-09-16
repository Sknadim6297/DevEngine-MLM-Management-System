<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_wallet_transactions', function (Blueprint $table) {
            $table->string('type', 20)->default('credit')->after('amount');
            $table->string('remarks')->nullable()->after('type');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('activation_wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'remarks']);
        });
    }
};
