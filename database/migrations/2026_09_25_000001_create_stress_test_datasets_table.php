<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stress_test_datasets', function (Blueprint $table) {
            $table->string('dataset_id')->primary();
            $table->string('root_member_id');
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('investment_count')->default(0);
            $table->string('status')->default('seeded');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stress_test_datasets');
    }
};