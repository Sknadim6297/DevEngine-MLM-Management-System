<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LevelIncomePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_bounded_pages_and_sql_aggregate_with_a_small_query_count(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->insertTransactions(125);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'time_ms' => $query->time];
        });

        $firstPageFromMemberIds = DB::table('level_commission_transactions')
            ->select('from_member_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('from_member_id')
            ->all();

        $this->assertNotEmpty($firstPageFromMemberIds);

        $response = $this->get(route('admin.report.level-income'));

        $response->assertOk()
            ->assertSee('Showing 1 to 50 of 125 entries', false)
            ->assertSee('<strong>125</strong>', false)
            ->assertSeeText($firstPageFromMemberIds[0]);

        $this->assertLessThanOrEqual(8, count($queries), json_encode($queries));
        $this->assertTrue(collect($queries)->contains(fn (array $query): bool => str_contains(strtolower($query['sql']), 'sum(')));

        $maxPageFromMemberIds = DB::table('level_commission_transactions')
            ->select('from_member_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('from_member_id')
            ->all();

        $this->assertNotEmpty($maxPageFromMemberIds);

        $this->get(route('admin.report.level-income', ['per_page' => 1000]))
            ->assertOk()
            ->assertSee('Showing 1 to 100 of 125 entries', false)
            ->assertSeeText($maxPageFromMemberIds[0]);

        if (isset($maxPageFromMemberIds[100])) {
            $this->assertDontSeeText($maxPageFromMemberIds[100]);
        }
    }

    public function test_level_income_filters_and_streamed_csv_preserve_results_and_order(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->insertTransactions(6);

        $match = DB::table('level_commission_transactions')
            ->select(['member_id', 'level', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($match);

        $memberId = $match->member_id;
        $level = (int) $match->level;
        $fromDate = date('Y-m-d', strtotime($match->created_at));
        $toDate = date('Y-m-d', strtotime('+1 day', strtotime($match->created_at)));

        $filteredMemberIds = DB::table('level_commission_transactions')
            ->where('member_id', $memberId)
            ->where('level', $level)
            ->where('created_at', '>=', $fromDate . ' 00:00:00')
            ->where('created_at', '<', $toDate . ' 00:00:00')
            ->select('member_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('member_id')
            ->all();

        $this->assertNotEmpty($filteredMemberIds);

        $filterParams = [
            'member_id' => $memberId,
            'level' => $level,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        $this->get(route('admin.report.level-income', $filterParams))->assertOk()
            ->assertSeeText($filteredMemberIds[0])
            ->assertSee('<strong>' . count($filteredMemberIds) . '</strong>', false);

        $response = $this->get(route('admin.report.level-income.export', $filterParams));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertNotSame('', trim($content));
        $this->assertStringContainsString($filteredMemberIds[0], $content);
        $this->assertSame(count($filteredMemberIds) + 1, substr_count(trim($content), "\n") + 1);
    }

    public function test_deep_page_requests_are_bounded_to_the_last_valid_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->insertTransactions(125);

        $response = $this->get(route('admin.report.level-income', ['page' => 5900, 'per_page' => 50]));

        $response->assertOk()
            ->assertSee('Showing 101 to 125 of 125 entries', false)
            ->assertDontSeeText('LC-BENCH-00001')
            ->assertDontSeeText('LC-BENCH-00050');
    }

    public function test_report_combines_total_amount_and_total_row_aggregate_into_a_single_query(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->insertTransactions(125);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('admin.report.level-income'))->assertOk();

        $aggregateQueries = array_values(array_filter($queries, function (string $sql): bool {
            $lower = strtolower($sql);

            return str_contains($lower, 'sum(') || str_contains($lower, 'count(');
        }));

        $this->assertCount(1, $aggregateQueries);
        $this->assertStringContainsString('sum(', strtolower($aggregateQueries[0]));
        $this->assertStringContainsString('count(', strtolower($aggregateQueries[0]));
    }

    private function insertTransactions(int $count): void
    {
        foreach (range(1, 10) as $memberNumber) {
            $memberId = 'STBENCH' . str_pad((string) $memberNumber, 4, '0', STR_PAD_LEFT);
            Member::create([
                'member_id' => $memberId,
                'sponsor_id' => 'STSOURCE',
                'sponsor_name' => 'Source Member',
                'member_name' => 'Benchmark Member ' . $memberNumber,
                'mobile_no' => (string) (9000000000 + $memberNumber),
                'pan_card_no' => 'BCH' . str_pad((string) $memberNumber, 7, '0', STR_PAD_LEFT),
                'email' => 'benchmark' . $memberNumber . '@example.test',
                'status' => 'active',
            ]);
        }

        $rows = [];
        for ($number = 1; $number <= $count; $number++) {
            $id = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $memberNumber = str_pad((string) (($number % 10) + 1), 4, '0', STR_PAD_LEFT);
            $createdAt = sprintf('2026-01-%02d 12:00:00', (($number - 1) % 6) + 1);
            $rows[] = [
                'reference' => 'LC-BENCH-' . $id,
                'investment_id' => 'INV-BENCH-' . $id,
                'member_id' => 'STBENCH' . $memberNumber,
                'member_name' => 'Benchmark Member ' . $memberNumber,
                'from_member_id' => 'STSOURCE' . $id,
                'from_member_name' => 'Source Member ' . $id,
                'level' => ($number % 3) + 1,
                'business_date' => substr($createdAt, 0, 10),
                'on_amount' => '100.0000',
                'rate_percentage' => '2.000',
                'income_amount' => '1.0000',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('level_commission_transactions')->insert($chunk);
        }
    }
}
