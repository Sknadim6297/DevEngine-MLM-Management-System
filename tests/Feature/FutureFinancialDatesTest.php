<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Services\RoiGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FutureFinancialDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_business_timestamp_columns_use_datetime(): void
    {
        $columns = [
            'members' => ['updated_at'],
            'investments' => ['created_at', 'updated_at', 'closed_at'],
            'roi_transactions' => ['created_at', 'updated_at'],
            'level_commission_transactions' => ['created_at', 'updated_at'],
            'rank_achievements' => ['created_at', 'updated_at'],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                $this->assertSame('datetime', Schema::getColumnType($table, $column), "{$table}.{$column}");
            }
        }

        $this->assertSame('datetime', Schema::getColumnType('rank_achievements', 'achieved_at'));
        $this->assertSame('date', Schema::getColumnType('roi_transactions', 'roi_date'));
        $this->assertSame('date', Schema::getColumnType('level_commission_transactions', 'business_date'));
    }

    public function test_future_investments_round_trip_across_kolkata_midnight_and_start_roi_next_day(): void
    {
        $service = app(RoiGenerationService::class);

        foreach ([2039, 2040, 2050] as $year) {
            $businessDate = CarbonImmutable::parse(sprintf('%d-01-01', $year), RoiGenerationService::TIMEZONE)->startOfDay();
            $investmentId = 'FUTURE-' . $year;
            $member = $this->createMember($year);
            $utcInvestmentTimestamp = $businessDate->setTimezone('UTC')->toDateTimeString();

            Investment::query()->create([
                'investment_id' => $investmentId,
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'amount' => '100.0000',
                'status' => 'active',
                'created_at' => $utcInvestmentTimestamp,
                'updated_at' => $utcInvestmentTimestamp,
            ]);

            $this->assertSame(
                $utcInvestmentTimestamp,
                DB::table('investments')->where('investment_id', $investmentId)->value('created_at')
            );
            $this->assertSame(
                $businessDate->toDateString(),
                CarbonImmutable::parse($utcInvestmentTimestamp, 'UTC')->setTimezone(RoiGenerationService::TIMEZONE)->toDateString()
            );

            $service->generateForDate($businessDate, $investmentId);
            $this->assertDatabaseMissing('roi_transactions', ['investment_id' => $investmentId]);

            $nextBusinessDate = $businessDate->addDay();
            $service->generateForDate($nextBusinessDate, $investmentId);

            $transaction = RoiTransaction::query()->where('investment_id', $investmentId)->firstOrFail();
            $this->assertSame($nextBusinessDate->toDateString(), $transaction->roi_date->toDateString());
            $this->assertNotNull(DB::table('roi_transactions')->where('investment_id', $investmentId)->value('created_at'));
        }
    }

    private function createMember(int $year): Member
    {
        return Member::query()->create([
            'member_id' => 'FUTURE-' . $year,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Future Date Member ' . $year,
            'mobile_no' => (string) (9000000000 + $year - 2000),
            'pan_card_no' => 'PAN' . $year . '000',
            'email' => 'future-' . $year . '@example.test',
            'status' => 'active',
        ]);
    }
}