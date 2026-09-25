<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Services\LevelCommissionGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LevelCommissionRankAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('lockedLevelProvider')]
    public function test_rank_locks_the_first_level_above_unlocked_limit(string $rank, string $business, int $lockedLevel): void
    {
        [$source, $target] = $this->chainToLevel($lockedLevel);
        $investment = $this->investment($source, 'INVLOCK' . $lockedLevel, $business);

        app(LevelCommissionGenerationService::class)->generateForInvestment($investment);

        $this->assertDatabaseMissing('level_commission_transactions', [
            'investment_id' => $investment->investment_id,
            'member_id' => $target->member_id,
            'level' => $lockedLevel,
        ]);
        $this->assertSame($rank, $this->rankNameFor($target));
    }

    public static function lockedLevelProvider(): array
    {
        return [
            'silver locks level 5' => ['Silver', '6000.0000', 5],
            'gold locks level 9' => ['Gold', '12000.0000', 9],
            'platinum locks level 13' => ['Platinum', '25000.0000', 13],
            'ruby locks level 17' => ['Ruby', '50000.0000', 17],
            'ruby club locks level 21' => ['Ruby Club', '100000.0000', 21],
            'diamond locks level 25' => ['Diamond', '250000.0000', 25],
            'blue diamond locks level 29' => ['Blue Diamond', '600000.0000', 29],
        ];
    }

    public function test_master_blaster_can_receive_level_32_without_changing_rate(): void
    {
        [$source, $target] = $this->chainToLevel(32);
        $investment = $this->investment($source, 'INVLOCK32', '1500000.0000');

        app(LevelCommissionGenerationService::class)->generateForInvestment($investment);

        $this->assertDatabaseHas('level_commission_transactions', [
            'investment_id' => $investment->investment_id,
            'member_id' => $target->member_id,
            'level' => 32,
            'rate_percentage' => '0.200',
        ]);
    }

    public function test_four_qualifying_direct_members_unlock_level_four_commission(): void
    {
        $receiver = $this->member('NADEEM001', 'ST666666');

        foreach (range(1, 4) as $number) {
            $directMember = $this->member('NADEEMD00' . $number, $receiver->member_id);
            $this->investment($directMember, 'INVNADEEM' . $number, '100.0000');
        }

        $firstLevelTwo = $this->member('NADEEML21', 'NADEEMD001');
        $firstLevelThree = $this->member('NADEEML31', $firstLevelTwo->member_id);
        $firstSource = $this->member('NADEEMS01', $firstLevelThree->member_id);
        $firstInvestment = $this->investment($firstSource, 'INVNADEEM400', '400.0000');

        $secondLevelTwo = $this->member('NADEEML22', 'NADEEMD002');
        $secondLevelThree = $this->member('NADEEML32', $secondLevelTwo->member_id);
        $secondSource = $this->member('NADEEMS02', $secondLevelThree->member_id);
        $secondInvestment = $this->investment($secondSource, 'INVNADEEM450', '450.0000');

        $rankData = app(\App\Services\RankService::class)->calculateForMember($receiver);

        $this->assertSame(4, $rankData['direct_qualifying_member_count']);
        $this->assertGreaterThanOrEqual(4, $rankData['unlocked_levels']);

        $service = app(LevelCommissionGenerationService::class);
        $service->generateForInvestment($firstInvestment);
        $service->generateForInvestment($secondInvestment);

        $this->assertDatabaseHas('level_commission_transactions', [
            'investment_id' => $firstInvestment->investment_id,
            'member_id' => $receiver->member_id,
            'from_member_id' => $firstSource->member_id,
            'level' => 4,
            'on_amount' => '400.0000',
            'rate_percentage' => '0.300',
            'income_amount' => '1.2000',
        ]);
        $this->assertDatabaseHas('level_commission_transactions', [
            'investment_id' => $secondInvestment->investment_id,
            'member_id' => $receiver->member_id,
            'from_member_id' => $secondSource->member_id,
            'level' => 4,
            'on_amount' => '450.0000',
            'rate_percentage' => '0.300',
            'income_amount' => '1.3500',
        ]);
    }

    private function chainToLevel(int $targetLevel): array
    {
        $target = $this->member('UP' . $targetLevel . '0', 'ST666666');
        $previous = $target;

        for ($level = 1; $level < $targetLevel; $level++) {
            $previous = $this->member('UP' . $targetLevel . $level, $previous->member_id);
        }

        $source = $this->member('SRC' . $targetLevel, $previous->member_id);

        return [$source, $target];
    }

    private function rankNameFor(Member $member): ?string
    {
        return app(\App\Services\RankService::class)->calculateForMember($member)['current_rank']?->name;
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member ' . $memberId,
            'mobile_no' => '98765' . substr(preg_replace('/[^0-9]/', '', $memberId), -5),
            'pan_card_no' => 'RANK' . substr(preg_replace('/[^0-9]/', '', $memberId), -6),
            'email' => strtolower($memberId) . '@example.test',
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $investmentId, string $amount): Investment
    {
        return Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => 'active',
        ]);
    }
}
