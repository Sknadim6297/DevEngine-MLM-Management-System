<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('rank_id')->nullable()->after('member_id');
            $table->foreign('rank_id')->references('id')->on('ranks')->nullOnDelete();
            $table->index('rank_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['rank_id']);
            $table->dropIndex(['rank_id']);
            $table->dropColumn('rank_id');
        });
    }
};
