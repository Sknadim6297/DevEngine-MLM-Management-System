<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use Illuminate\Console\Command;

class RankProgressionTestData extends Command
{
    protected $signature = 'rank:test-dataset';

    protected $description = 'Reset the demo progression dataset to a clean, deterministic 10-row rank history.';

    private const DEMO_MEMBER_IDS = ['DEMO-101', 'DEMO-102', 'DEMO-103', 'DEMO-104'];

    public function handle(): int
    {
        RankAchievement::query()->delete();
        Member::query()->update(['rank_id' => null]);

        $finalRankId = Rank::query()->where('name', 'Master Blaster')->value('id');
        $blueDiamondRankId = Rank::query()->where('name', 'Blue Diamond')->value('id');
        $diamondRankId = Rank::query()->where('name', 'Diamond')->value('id');

        $plan = [
            'DEMO-101' => ['Diamond', 'Blue Diamond', 'Master Blaster'],
            'DEMO-102' => ['Diamond', 'Blue Diamond', 'Master Blaster'],
            'DEMO-103' => ['Diamond', 'Blue Diamond'],
            'DEMO-104' => ['Diamond', 'Blue Diamond'],
        ];

        foreach ($plan as $memberId => $ranks) {
            $member = Member::query()->firstOrCreate(
                ['member_id' => $memberId],
                [
                    'sponsor_id' => 'ST666666',
                    'sponsor_name' => 'Admin',
                    'member_name' => 'Demo Member ' . $memberId,
                    'mobile_no' => '98765' . substr($memberId, -5),
                    'pan_card_no' => 'RANK' . substr($memberId, -5),
                    'email' => strtolower($memberId) . '@example.test',
                    'status' => 'active',
                ]
            );

            foreach ($ranks as $index => $rankName) {
                $rank = Rank::query()->where('name', $rankName)->firstOrFail();

                RankAchievement::query()->updateOrCreate(
                    ['member_id' => $memberId, 'rank_id' => $rank->id],
                    [
                        'member_name' => $member->member_name,
                        'qualifying_business_amount' => match ($rankName) {
                            'Diamond' => '250000.0000',
                            'Blue Diamond' => '600000.0000',
                            'Master Blaster' => '1500000.0000',
                            default => '0.0000',
                        },
                        'achieved_at' => now('Asia/Kolkata')->subDays(5 - $index),
                    ]
                );
            }

            $member->forceFill(['rank_id' => $finalRankId])->saveQuietly();
        }

        $this->info('Reset rank progression test dataset with 10 achievement rows for the demo members.');

        return self::SUCCESS;
    }
}
