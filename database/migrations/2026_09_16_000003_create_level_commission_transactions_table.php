<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_commission_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('investment_id');
            $table->string('member_id');
            $table->string('member_name');
            $table->string('from_member_id');
            $table->string('from_member_name');
            $table->unsignedTinyInteger('level');
            $table->decimal('on_amount', 20, 4);
            $table->decimal('rate_percentage', 6, 3);
            $table->decimal('income_amount', 20, 4);
            $table->timestamps();

            $table->unique(['investment_id', 'member_id'], 'level_commission_investment_member_unique');
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_commission_transactions');
    }
};
