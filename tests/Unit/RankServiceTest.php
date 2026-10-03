<?php

namespace Tests\Unit;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Services\RankService;
use Carbon\CarbonImmutable;
use Database\Seeders\RankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RankServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankSeeder::class);
    }

    #[DataProvider('rankThresholdProvider')]
    public function test_rank_thresholds_are_database_driven(string $business, ?string $rank, int $levels): void
    {
        $member = $this->member('STROOT' . $levels, 'Root ' . $levels, 'ST666666');
        $child = $this->member('STCHILD' . $levels, 'Child ' . $levels, $member->member_id);
        $this->investment($child, 'INV' . $levels, $business);

        $result = app(RankService::class)->calculateForMember($member);

        $this->assertSame($business, $result['full_team_business']);
        $this->assertSame($rank, $result['current_rank']?->name);
        $this->assertSame($levels, $result['unlocked_levels']);
    }

    public static function rankThresholdProvider(): array
    {
        return [
            'below silver' => ['5999.9999', null, 0],
            'silver' => ['6000.0000', 'Silver', 4],
            'gold' => ['12000.0000', 'Gold', 8],
            'platinum' => ['25000.0000', 'Platinum', 12],
            'ruby' => ['50000.0000', 'Ruby', 16],
            'ruby club' => ['100000.0000', 'Ruby Club', 20],
            'diamond' => ['250000.0000', 'Diamond', 24],
            'blue diamond' => ['600000.0000', 'Blue Diamond', 28],
            'master blaster' => ['1500000.0000', 'Master Blaster', 32],
            'above master blaster' => ['1750000.0000', 'Master Blaster', 32],
        ];
    }

    public function test_full_team_business_includes_direct_and_indirect_members_once(): void
    {
        $root = $this->member('STROOT001', 'Root Member', 'ST666666');
        $direct = $this->member('STDIRECT1', 'Direct Member', $root->member_id);
        $indirect = $this->member('STINDIRECT1', 'Indirect Member', $direct->member_id);
        $this->investment($direct, 'INVDIRECT1', '4000.0000');
        $this->investment($indirect, 'INVINDIRECT1', '2500.0000');
        $this->investment($root, 'INVROOT001', '9000.0000');

        $result = app(RankService::class)->calculateForMember($root);

        $this->assertSame('15500.0000', $result['full_team_business']);
        $this->assertSame('Gold', $result['current_rank']?->name);
        $this->assertSame('9500.0000', $result['remaining_business']);
        $this->assertSame(['STROOT001', 'STDIRECT1', 'STINDIRECT1'], $result['team_member_ids']);
    }

    public function test_cycle_and_duplicate_references_do_not_loop_or_duplicate_business(): void
    {
        $root = $this->member('STCYCLE01', 'Cycle Root', 'STCYCLE02');
        $child = $this->member('STCYCLE02', 'Cycle Child', $root->member_id);
        $this->investment($child, 'INVCYCLE1', '6000.0000');

        $result = app(RankService::class)->calculateForMember($root);

        $this->assertSame('6000.0000', $result['full_team_business']);
        $this->assertSame('Silver', $result['current_rank']?->name);
        $this->assertSame(['STCYCLE01', 'STCYCLE02'], $result['team_member_ids']);
    }

    public function test_only_active_qualifying_investments_count_as_business(): void
    {
        $root = $this->member('STROOT002', 'Root Member 2', 'ST666666');
        $child = $this->member('STCHILD002', 'Child Member 2', $root->member_id);
        $this->investment($child, 'INVACTIVE2', '100.0000', 'active');
        $this->investment($child, 'INVEXPIRED2', '6000.0000', 'expired');
        $this->investment($child, 'INVSML2', '99.9999', 'active');

        $result = app(RankService::class)->calculateForMember($root);

        $this->assertSame('100.0000', $result['full_team_business']);
        $this->assertNull($result['current_rank']);
        $this->assertSame('5900.0000', $result['remaining_business']);
    }

    public function test_future_direct_investments_do_not_unlock_level_four_before_their_business_date(): void
    {
        $root = $this->member('STROOT2040', 'Future Rank Root', 'ST666666');
        $investmentDate = CarbonImmutable::parse('2040-01-01', 'Asia/Kolkata')
            ->startOfDay()
            ->setTimezone('UTC');

        foreach (range(1, 4) as $number) {
            $child = $this->member('STCHILD204' . $number, 'Future Rank Child ' . $number, $root->member_id);
            $investment = $this->investment($child, 'INV2040' . $number, '100.0000');
            $investment->forceFill([
                'created_at' => $investmentDate,
                'updated_at' => $investmentDate,
            ])->save();
        }

        $beforeBusinessDate = app(RankService::class)->calculateForMember($root, null, '2039-12-31');
        $onBusinessDate = app(RankService::class)->calculateForMember($root, null, '2040-01-01');

        $this->assertSame('0.0000', $beforeBusinessDate['full_team_business']);
        $this->assertSame(0, $beforeBusinessDate['direct_qualifying_member_count']);
        $this->assertSame(0, $beforeBusinessDate['unlocked_levels']);
        $this->assertSame('400.0000', $onBusinessDate['full_team_business']);
        $this->assertSame(4, $onBusinessDate['direct_qualifying_member_count']);
        $this->assertSame(4, $onBusinessDate['unlocked_levels']);
    }

    private function member(string $memberId, string $name, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => $name,
            'mobile_no' => '98765' . substr(preg_replace('/[^0-9]/', '', $memberId), -5),
            'pan_card_no' => 'RANK' . substr(preg_replace('/[^0-9]/', '', $memberId), -6),
            'email' => strtolower($memberId) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $investmentId, string $amount, string $status = 'active'): Investment
    {
        return Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => $status,
        ]);
    }
}
