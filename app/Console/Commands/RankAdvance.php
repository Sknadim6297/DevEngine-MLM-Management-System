<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use App\Services\RankService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RankAdvance extends Command
{
    private const ACHIEVEMENT_INSERT_BATCH_SIZE = 500;

    protected $signature = 'rank:advance {--member-id= : Limit the one-step advancement to a specific member} {--member-prefix= : Limit processing to a member ID prefix}';

    protected $description = 'Advance each qualifying demo member by exactly one rank using the real rank rules.';

    public function handle(RankService $rankService): int
    {
        $startedAt = microtime(true);
        Log::info('Scheduled rank advancement started.');

        $memberIds = $this->memberIds();
        if ($memberIds === []) {
            $this->info('Rank reconciliation completed for 0 member(s).');
            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $members = Member::query()
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'member_name', 'rank_id']);
        $memberIds = $members->pluck('member_id')->all();
        $ranks = Rank::query()->orderBy('sort_order')->get()->keyBy('id');
        $activeRanks = $ranks->where('is_active', true)->values();
        $calculations = $rankService->calculateForMembers($memberIds);
        $nextRankByCurrentRank = $this->nextRanks($activeRanks);
        $achievementKeys = RankAchievement::query()
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'rank_id'])
            ->mapWithKeys(fn (RankAchievement $achievement): array => [$this->achievementKey($achievement->member_id, $achievement->rank_id) => true]);
        $rankUpdates = [];
        $newAchievements = [];
        $changed = 0;

        foreach ($members as $member) {
            $memberId = $member->member_id;
            $calculation = $calculations[$memberId];
            $fullTeamBusiness = (string) $calculation['full_team_business'];
            $qualifiedRank = $calculation['current_rank'];
            $currentRank = $member->rank_id !== null ? $ranks->get($member->rank_id) : null;
            $nextRank = $currentRank ? ($nextRankByCurrentRank[$currentRank->id] ?? null) : $activeRanks->first();
            $desiredRankId = $currentRank?->id;

            if ($currentRank === null) {
                if ($nextRank !== null && bccomp($fullTeamBusiness, (string) $nextRank->required_full_team_business, 4) >= 0) {
                    $desiredRankId = $nextRank->id;
                    $this->queueAchievement($newAchievements, $achievementKeys, $member, $nextRank, $fullTeamBusiness);
                } else {
                    $desiredRankId = null;
                }
            } elseif ($nextRank !== null && bccomp($fullTeamBusiness, (string) $nextRank->required_full_team_business, 4) >= 0) {
                $desiredRankId = $nextRank->id;
                $this->queueAchievement($newAchievements, $achievementKeys, $member, $nextRank, $fullTeamBusiness);
            } elseif ($qualifiedRank !== null && $qualifiedRank->id !== $currentRank->id) {
                $desiredRankId = $qualifiedRank->id;
            } elseif ($qualifiedRank === null) {
                $desiredRankId = null;
            } else {
                $desiredRankId = $qualifiedRank->id;
            }

            if ($desiredRankId !== $member->rank_id) {
                $rankUpdates[(string) ($member->rank_id ?? 'null')][(string) ($desiredRankId ?? 'null')][] = $memberId;
                $changed++;
            }
        }

        DB::transaction(function () use ($rankUpdates, $newAchievements): void {
            foreach ($rankUpdates as $currentRankId => $updatesByRankId) {
                foreach ($updatesByRankId as $rankId => $ids) {
                    $query = Member::query()->whereIn('member_id', $ids);
                    if ($currentRankId === 'null') {
                        $query->whereNull('rank_id');
                    } else {
                        $query->where('rank_id', (int) $currentRankId);
                    }

                    $query->update([
                        'rank_id' => $rankId === 'null' ? null : (int) $rankId,
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($newAchievements !== []) {
                foreach (array_chunk($newAchievements, self::ACHIEVEMENT_INSERT_BATCH_SIZE) as $achievementBatch) {
                    RankAchievement::query()->insertOrIgnore($achievementBatch);
                }
            }
        });

        $this->info(sprintf(
            'Rank reconciliation completed for %d member(s): %d rank updates, %d achievements, %.3f seconds.',
            count($members), $changed, count($newAchievements), microtime(true) - $startedAt
        ));
        Log::info('Scheduled rank advancement finished.', ['processed' => count($members), 'rank_updates' => $changed, 'achievements' => count($newAchievements), 'duration_seconds' => round(microtime(true) - $startedAt, 3)]);

        return self::SUCCESS;
    }

    private function queueAchievement(array &$newAchievements, Collection &$achievementKeys, Member $member, Rank $rank, string $fullTeamBusiness): void
    {
        $key = $this->achievementKey($member->member_id, $rank->id);
        if (! isset($achievementKeys[$key])) {
            $now = now('Asia/Kolkata');
            $newAchievements[] = [
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'rank_id' => $rank->id,
                'qualifying_business_amount' => $fullTeamBusiness,
                'achieved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $achievementKeys[$key] = true;
        }
    }

    private function achievementKey(string $memberId, int $rankId): string
    {
        return $memberId . ':' . $rankId;
    }

    private function nextRanks(Collection $activeRanks): array
    {
        $nextRanks = [];
        foreach ($activeRanks as $index => $rank) {
            $nextRanks[$rank->id] = $activeRanks->get($index + 1);
        }

        return $nextRanks;
    }

    private function memberIds(): array
    {
        $selected = trim((string) $this->option('member-id'));

        if ($selected !== '') {
            return [$selected];
        }

        $prefix = trim((string) $this->option('member-prefix'));

        return $prefix !== ''
            ? Member::query()->where('member_id', 'like', $prefix . '%')->pluck('member_id')->all()
            : Member::query()->pluck('member_id')->all();
    }
}
