<?php

namespace Tests\Feature;

use App\Models\LevelCommissionTransaction;
use App\Services\RankService;
use Database\Seeders\RankSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LevelCommissionRankPreparationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const INVESTMENTS = 20080;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankSeeder::class);
    }

    public function test_rank_preparation_for_20k_investments_is_bounded_and_fast(): void
    {
        $this->seedGenealogy(self::INVESTMENTS);
        $memberIds = DB::table('investments')->where('status', 'active')->orderBy('id')->pluck('member_id');
        $this->assertCount(self::INVESTMENTS, $memberIds);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $start = microtime(true);
        $service = app(\App\Services\LevelCommissionGenerationService::class);
        foreach ($memberIds->unique()->values()->chunk(1000) as $chunk) {
            $service->prepareForInvestments($chunk, \Carbon\CarbonImmutable::parse('2026-01-01', 'Asia/Kolkata'));
        }
        $elapsed = microtime(true) - $start;
        fwrite(STDERR, sprintf("\n[perf] prepare: %.2fs, %d queries, peak %.1f MB\n", $elapsed, $queries, memory_get_peak_usage(true) / 1048576));

        $this->assertLessThan(30, $elapsed);
        $this->assertLessThanOrEqual(21 * 5 + 5, $queries);
    }
    public function test_rank_calculation_for_large_member_set_uses_constant_query_count_and_correct_results(): void
    {
        $this->seedGenealogy(self::INVESTMENTS);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $memberIds = ['ST000001', 'ST000002', 'ST000010'];
        $start = microtime(true);
        $results = app(RankService::class)->calculateForMembers($memberIds);
        $elapsed = microtime(true) - $start;
        fwrite(STDERR, sprintf("\n[perf] rank calc: %.2fs, %d queries, peak %.1f MB\n", $elapsed, $queries, memory_get_peak_usage(true) / 1048576));

        $this->assertLessThanOrEqual(6, $queries);
        // Root team = every member (each holds one 100.0000 investment).
        $this->assertSame(number_format(self::INVESTMENTS * 100, 4, '.', ''), $results['ST000001']['full_team_business']);
        $this->assertSame(self::INVESTMENTS, count($results['ST000001']['team_member_ids']));
        $this->assertSame('ST000001', $results['ST000001']['team_member_ids'][0]);
        $this->assertSame(32, $results['ST000001']['unlocked_levels']);
    }

    private function seedGenealogy(int $count): void
    {
        $now = now()->toDateTimeString();
        $members = [];
        $investments = [];
        for ($i = 1; $i <= $count; $i++) {
            $id = sprintf('ST%06d', $i);
            $members[] = [
                'member_id' => $id,
                'sponsor_id' => $i === 1 ? 'ST666666' : sprintf('ST%06d', intdiv($i - 2, 3) + 1),
                'sponsor_name' => 'Sponsor',
                'member_name' => 'Member ' . $i,
                'mobile_no' => (string) (9000000000 + $i),
                'pan_card_no' => sprintf('PAN%07d', $i),
                'email' => "m{$i}@example.test",
                'password' => 'x',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $investments[] = [
                'investment_id' => sprintf('INV%06d', $i),
                'member_id' => $id,
                'member_name' => 'Member ' . $i,
                'amount' => '100.0000',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($members, 200) as $chunk) {
            DB::table('members')->insert($chunk);
        }
        foreach (array_chunk($investments, 200) as $chunk) {
            DB::table('investments')->insert($chunk);
        }
    }
}
