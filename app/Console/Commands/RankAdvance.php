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
        $processed = 0;

        foreach ($memberIds as $memberId) {
            $member = Member::query()->where('member_id', $memberId)->first();

            if (! $member) {
                $this->warn("{$memberId} does not exist. Skipping.");
                continue;
            }

            $processed++;
            $currentRank = $member->rank()->first();
            $calculation = $rankService->calculateForMember($member);
            $fullTeamBusiness = (string) $calculation['full_team_business'];
            $highestQualifiedRank = $rankService->highestQualifiedRank($member);

            if ($currentRank === null) {
                $nextRank = $this->firstRank();

                if ($nextRank === null || bccomp($fullTeamBusiness, (string) $nextRank->required_full_team_business, 4) < 0) {
                    $member->forceFill(['rank_id' => null])->saveQuietly();
                    continue;
                }

                $this->promoteMember($member, $nextRank, $fullTeamBusiness);
                continue;
            }

            $nextRank = $this->nextRank($currentRank);
            if ($nextRank !== null && bccomp($fullTeamBusiness, (string) $nextRank->required_full_team_business, 4) >= 0) {
                $this->promoteMember($member, $nextRank, $fullTeamBusiness);
                continue;
            }

            if ($highestQualifiedRank === null) {
                $member->forceFill(['rank_id' => null])->saveQuietly();
                $this->line(sprintf('%s | Current Rank: %s | Business: %s | Status: Rank cleared', $memberId, $currentRank->name, $fullTeamBusiness));
                continue;
            }

            if ($currentRank->id !== $highestQualifiedRank->id) {
                $member->forceFill(['rank_id' => $highestQualifiedRank->id])->saveQuietly();
                $this->line(sprintf('%s | Current Rank: %s | Reconciled Rank: %s | Status: Reconciled to highest qualified rank', $memberId, $currentRank->name, $highestQualifiedRank->name));
                continue;
            }

            $member->forceFill(['rank_id' => $highestQualifiedRank->id])->saveQuietly();
        }

        $this->info('Rank reconciliation completed for ' . $processed . ' member(s).');

        return self::SUCCESS;
    }

    private function promoteMember(Member $member, Rank $nextRank, string $fullTeamBusiness): void
    {
        $alreadyAchieved = RankAchievement::query()
            ->where('member_id', $member->member_id)
            ->where('rank_id', $nextRank->id)
            ->exists();

        if (! $alreadyAchieved) {
            RankAchievement::query()->create([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'rank_id' => $nextRank->id,
                'qualifying_business_amount' => $fullTeamBusiness,
                'achieved_at' => now('Asia/Kolkata'),
            ]);
        }

        $member->forceFill(['rank_id' => $nextRank->id])->saveQuietly();
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

    private function firstRank(): ?Rank
    {
        return Rank::query()->where('is_active', true)->orderBy('sort_order')->first();
    }

    private function memberIds(): array
    {
        $selected = trim((string) $this->option('member-id'));

        if ($selected !== '') {
            return [$selected];
        }

        return Member::query()->pluck('member_id')->all();
    }
}
