<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_summary_generations')) {
            Schema::create('report_summary_generations', function (Blueprint $table): void {
                $table->id();
                $table->string('status', 20)->default('building');
                $table->unsignedBigInteger('roi_last_transaction_id')->default(0);
                $table->unsignedBigInteger('level_last_transaction_id')->default(0);
                $table->unsignedBigInteger('roi_source_max_id')->default(0);
                $table->unsignedBigInteger('level_source_max_id')->default(0);
                $table->string('source_timezone', 64)->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('report_summary_state')) {
            Schema::create('report_summary_state', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->foreignId('active_generation_id')->nullable()
                    ->constrained('report_summary_generations')->nullOnDelete();
                $table->boolean('writes_enabled')->default(false);
                $table->boolean('rebuild_in_progress')->default(false);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('report_summary_state', 'rebuild_in_progress')) {
            Schema::table('report_summary_state', function (Blueprint $table): void {
                $table->boolean('rebuild_in_progress')->default(false);
            });
        }

        DB::table('report_summary_state')->insertOrIgnore([
            'id' => 1,
            'active_generation_id' => null,
            'writes_enabled' => false,
            'rebuild_in_progress' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! Schema::hasTable('roi_report_global_daily_summaries')) {
            Schema::create('roi_report_global_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generation_id');
                $table->unsignedInteger('date_key')->default(0);
                $table->unsignedBigInteger('transaction_count')->default(0);
                $table->decimal('total_income_amount', 38, 4)->default(0);
                $table->unique(['generation_id', 'date_key'], 'roi_global_daily_generation_date_unique');
                $table->foreign('generation_id', 'roi_global_daily_gen_fk')->references('id')->on('report_summary_generations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('roi_report_member_daily_summaries')) {
            Schema::create('roi_report_member_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generation_id');
                $table->string('member_id');
                $table->unsignedInteger('date_key')->default(0);
                $table->unsignedBigInteger('transaction_count')->default(0);
                $table->decimal('total_income_amount', 38, 4)->default(0);
                $table->unique(['generation_id', 'member_id', 'date_key'], 'roi_member_daily_generation_member_date_unique');
                $table->foreign('generation_id', 'roi_member_daily_gen_fk')->references('id')->on('report_summary_generations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('level_commission_report_global_daily_summaries')) {
            Schema::create('level_commission_report_global_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generation_id');
                $table->unsignedInteger('date_key')->default(0);
                $table->unsignedBigInteger('transaction_count')->default(0);
                $table->decimal('total_income_amount', 38, 4)->default(0);
                $table->unique(['generation_id', 'date_key'], 'lc_global_daily_generation_date_unique');
                $table->foreign('generation_id', 'lc_global_daily_gen_fk')->references('id')->on('report_summary_generations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('level_commission_report_level_daily_summaries')) {
            Schema::create('level_commission_report_level_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generation_id');
                $table->unsignedTinyInteger('level');
                $table->unsignedInteger('date_key')->default(0);
                $table->unsignedBigInteger('transaction_count')->default(0);
                $table->decimal('total_income_amount', 38, 4)->default(0);
                $table->unique(['generation_id', 'level', 'date_key'], 'lc_level_daily_generation_level_date_unique');
                $table->foreign('generation_id', 'lc_level_daily_gen_fk')->references('id')->on('report_summary_generations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('level_commission_report_member_level_daily_summaries')) {
            Schema::create('level_commission_report_member_level_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generation_id');
                $table->string('member_id');
                $table->unsignedTinyInteger('level');
                $table->unsignedInteger('date_key')->default(0);
                $table->unsignedBigInteger('transaction_count')->default(0);
                $table->decimal('total_income_amount', 38, 4)->default(0);
                $table->unique(
                    ['generation_id', 'member_id', 'level', 'date_key'],
                    'lc_member_level_daily_generation_member_level_date_unique'
                );
                $table->foreign('generation_id', 'lc_member_level_daily_gen_fk')->references('id')->on('report_summary_generations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('level_commission_report_member_level_daily_summaries');
        Schema::dropIfExists('level_commission_report_level_daily_summaries');
        Schema::dropIfExists('level_commission_report_global_daily_summaries');
        Schema::dropIfExists('roi_report_member_daily_summaries');
        Schema::dropIfExists('roi_report_global_daily_summaries');
        Schema::dropIfExists('report_summary_state');
        Schema::dropIfExists('report_summary_generations');
    }
};