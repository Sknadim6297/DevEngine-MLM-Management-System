<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roi_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('investment_id');
            $table->string('member_id');
            $table->string('member_name');
            $table->decimal('on_amount', 20, 4);
            $table->decimal('rate_percentage', 6, 3)->nullable();
            $table->decimal('income_amount', 20, 4);
            $table->date('roi_date');
            $table->timestamps();

            $table->unique(['investment_id', 'roi_date'], 'roi_investment_date_unique');
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roi_transactions');
    }
};
