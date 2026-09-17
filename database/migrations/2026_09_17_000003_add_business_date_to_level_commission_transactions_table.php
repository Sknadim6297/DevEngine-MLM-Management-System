<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_commission_transactions', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('level');
        });

        DB::statement('UPDATE level_commission_transactions SET business_date = DATE(created_at) WHERE business_date IS NULL');

        Schema::table('level_commission_transactions', function (Blueprint $table) {
            $table->dropUnique('level_commission_investment_member_unique');
            $table->unique(
                ['investment_id', 'member_id', 'business_date'],
                'level_commission_investment_member_date_unique'
            );
            $table->index(['investment_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::table('level_commission_transactions', function (Blueprint $table) {
            $table->dropUnique('level_commission_investment_member_date_unique');
            $table->dropIndex('level_commission_transactions_investment_id_business_date_index');
            $table->dropColumn('business_date');
            $table->unique(
                ['investment_id', 'member_id'],
                'level_commission_investment_member_unique'
            );
        });
    }
};