<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('withdrawal_id')->unique();
            $table->string('member_id');
            $table->string('member_name');
            $table->string('investment_id')->unique();
            $table->decimal('investment_amount', 20, 4);
            $table->decimal('withdrawal_amount', 20, 4);
            $table->string('status', 20)->default('pending');
            $table->timestamp('withdrawn_at');
            $table->timestamps();

            $table->index(['member_id', 'status', 'withdrawn_at']);
            $table->index(['investment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_withdrawals');
    }
};
