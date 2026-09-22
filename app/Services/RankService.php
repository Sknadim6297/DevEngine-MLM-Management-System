<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RankService
{
    private const MONEY_SCALE = 4;

    public function calculateForMember(Member|string $member): array
    {
        $memberId = $member instanceof Member ? $member->member_id : $member;
        return $this->calculateForMembers([$memberId])[$memberId];
    }

    public function syncMemberRankAchievement(Member $member): ?RankAchievement
    {
        $rankData = $this->calculateForMember($member);
        $currentRank = $rankData['current_rank'];

        if ($currentRank === null) {
            $member->forceFill(['rank_id' => null])->saveQuietly();

            return null;
        }

        $member->forceFill(['rank_id' => $currentRank->id])->saveQuietly();

        $achievedRank = null;
        foreach (Rank::query()->where('is_active', true)->orderBy('sort_order')->get() as $rank) {
            if (bccomp($rankData['full_team_business'], (string) $rank->required_full_team_business, self::MONEY_SCALE) < 0) {
                continue;
            }

            $existing = RankAchievement::query()
                ->where('member_id', $member->member_id)
                ->where('rank_id', $rank->id)
                ->first();

            if ($existing) {
                $achievedRank = $existing;
                continue;
            }

            $achievedRank = RankAchievement::query()->create([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'rank_id' => $rank->id,
                'qualifying_business_amount' => $rankData['full_team_business'],
                'achieved_at' => now('Asia/Kolkata'),
            ]);
        }

        return $achievedRank;
    }

    public function syncMemberRankAchievements(array $memberIds): void
    {
        if ($memberIds === []) {
            return;
        }

        $members = Member::query()->whereIn('member_id', $memberIds)->get();

        foreach ($members as $member) {
            $this->syncMemberRankAchievement($member);
        }
    }

    public function calculateForMembers(array $memberIds): array
    {
        $members = Member::query()
            ->select(['member_id', 'sponsor_id'])
            ->get();

        $knownMemberIds = $members->pluck('member_id')->all();
        foreach ($memberIds as $memberId) {
            if (! in_array($memberId, $knownMemberIds, true)) {
                throw new InvalidArgumentException('The requested member does not exist.');
            }
        }

        $childrenBySponsor = $members->groupBy('sponsor_id');
        $allDownlineIds = [];
        $downlineIdsByMember = [];
        foreach ($memberIds as $memberId) {
            $downlineIdsByMember[$memberId] = $this->downlineIds($memberId, $childrenBySponsor);
            $allDownlineIds = array_merge($allDownlineIds, $downlineIdsByMember[$memberId]);
        }

        $allDownlineIds = array_values(array_unique($allDownlineIds));
        $businessByMember = Investment::query()
            ->whereIn('member_id', $allDownlineIds)
            ->where('status', 'active')
            ->where('amount', '>=', 100)
            ->selectRaw('member_id, SUM(amount) as business')
            ->groupBy('member_id')
            ->pluck('business', 'member_id');

        $ranks = Rank::query()->where('is_active', true)->orderBy('sort_order')->get();
        $results = [];
        foreach ($memberIds as $memberId) {
            $fullTeamBusiness = '0.0000';
            foreach ($downlineIdsByMember[$memberId] as $downlineId) {
                $fullTeamBusiness = bcadd($fullTeamBusiness, (string) ($businessByMember->get($downlineId, '0.0000')), self::MONEY_SCALE);
            }

            $currentRank = null;
            $nextRank = null;
            foreach ($ranks as $rank) {
                if (bccomp($fullTeamBusiness, (string) $rank->required_full_team_business, self::MONEY_SCALE) >= 0) {
                    $currentRank = $rank;
                    continue;
                }

                $nextRank = $rank;
                break;
            }

            $remainingBusiness = $nextRank === null ? '0.0000' : bcsub((string) $nextRank->required_full_team_business, $fullTeamBusiness, self::MONEY_SCALE);
            $progress = $nextRank === null ? '100.0000' : bcmul(bcdiv($fullTeamBusiness, (string) $nextRank->required_full_team_business, 8), '100', self::MONEY_SCALE);
            $results[$memberId] = [
                'member_id' => $memberId,
                'full_team_business' => $fullTeamBusiness,
                'current_rank' => $currentRank,
                'unlocked_levels' => $currentRank?->unlocked_levels ?? 0,
                'next_rank' => $nextRank,
                'remaining_business' => $remainingBusiness,
                'rank_progress' => $progress,
                'team_member_ids' => $downlineIdsByMember[$memberId],
            ];
        }

        return $results;
    }

    private function downlineIds(string $rootMemberId, Collection $childrenBySponsor): array
    {
        $downlineIds = [];
        $visited = [$rootMemberId => true];
        $pending = [$rootMemberId];

        while ($pending !== []) {
            $sponsorId = array_shift($pending);

            foreach ($childrenBySponsor->get($sponsorId, collect()) as $child) {
                if (isset($visited[$child->member_id])) {
                    continue;
                }

                $visited[$child->member_id] = true;
                $downlineIds[] = $child->member_id;
                $pending[] = $child->member_id;
            }
        }

        return $downlineIds;
    }
}
