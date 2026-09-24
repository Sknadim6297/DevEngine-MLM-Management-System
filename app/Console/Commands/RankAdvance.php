<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use App\Services\RankService;
use Illuminate\Console\Command;

class RankAdvance extends Command
{
    protected $signature = 'rank:advance {--member-id= : Limit the one-step advancement to a specific member}';

    protected $description = 'Advance each qualifying demo member by exactly one rank using the real rank rules.';

    public function handle(RankService $rankService): int
    {
        $memberIds = $this->memberIds();
        $advanced = 0;

        foreach ($memberIds as $memberId) {
            $member = Member::query()->where('member_id', $memberId)->first();

            if (! $member) {
                $this->warn("{$memberId} does not exist. Skipping.");
                continue;
            }

            $currentRank = $member->rank()->first();
            $nextRank = $this->nextRank($currentRank);

            if ($nextRank === null) {
                $this->line(sprintf('%s | Current Rank: %s | Next Rank: N/A | Status: Already at highest rank', $memberId, $currentRank?->name ?? 'Unranked'));
                continue;
            }

            $calculation = $rankService->calculateForMember($member);
            $fullTeamBusiness = (string) $calculation['full_team_business'];
            $requiredBusiness = (string) $nextRank->required_full_team_business;

            if (bccomp($fullTeamBusiness, $requiredBusiness, 4) < 0) {
                $this->line(sprintf(
                    '%s | Current Rank: %s | Next Rank: %s | Required Test Data: %s / Status: Waiting',
                    $memberId,
                    $currentRank?->name ?? 'Unranked',
                    $nextRank->name,
                    $requiredBusiness
                ));
                continue;
            }

            $alreadyAchieved = RankAchievement::query()
                ->where('member_id', $memberId)
                ->where('rank_id', $nextRank->id)
                ->exists();

            if (! $alreadyAchieved) {
                RankAchievement::query()->create([
                    'member_id' => $memberId,
                    'member_name' => $member->member_name,
                    'rank_id' => $nextRank->id,
                    'qualifying_business_amount' => $fullTeamBusiness,
                    'achieved_at' => now('Asia/Kolkata'),
                ]);
            }

            $member->forceFill(['rank_id' => $nextRank->id])->saveQuietly();
            $advanced++;

            $this->info(sprintf(
                '%s | %s -> %s | Business: %s | Status: Advanced one step',
                $memberId,
                $currentRank?->name ?? 'Unranked',
                $nextRank->name,
                $fullTeamBusiness
            ));
        }

        $this->info('One-step rank advancement completed for ' . $advanced . ' demo member(s).');

        return self::SUCCESS;
    }

    private function nextRank(?Rank $currentRank): ?Rank
    {
        $ranks = Rank::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($currentRank === null) {
            return $ranks->first();
        }

        foreach ($ranks as $rank) {
            if ($rank->sort_order > $currentRank->sort_order) {
                return $rank;
            }
        }

        return null;
    }

    private function memberIds(): array
    {
        $selected = trim((string) $this->option('member-id'));

        return $selected !== '' ? [$selected] : ['DEMO-101', 'DEMO-102', 'DEMO-103', 'DEMO-104'];
    }
}
