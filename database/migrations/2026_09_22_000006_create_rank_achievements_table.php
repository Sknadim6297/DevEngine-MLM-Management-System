<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_achievements', function (Blueprint $table) {
            $table->id();
            $table->string('member_id');
            $table->string('member_name');
            $table->unsignedBigInteger('rank_id');
            $table->decimal('qualifying_business_amount', 20, 4)->default(0);
            $table->dateTime('achieved_at');
            $table->timestamps();

            $table->unique(['member_id', 'rank_id'], 'rank_achievement_member_rank_unique');
            $table->index(['member_id', 'achieved_at']);
            $table->index('rank_id');

            $table->foreign('rank_id')->references('id')->on('ranks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_achievements');
    }
};
