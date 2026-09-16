<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roi_transactions', function (Blueprint $table) {
            $table->string('status', 20)->default('generated')->after('income_amount');
            $table->date('withdrawable_on')->nullable()->after('roi_date');
            $table->index(['member_id', 'status', 'withdrawable_on']);
        });
    }

    public function down(): void
    {
        Schema::table('roi_transactions', function (Blueprint $table) {
            $table->dropIndex(['member_id', 'status', 'withdrawable_on']);
            $table->dropColumn(['status', 'withdrawable_on']);
        });
    }
};
