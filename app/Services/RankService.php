<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RankService
{
    private const MONEY_SCALE = 4;

    private const DIRECT_MEMBER_LEVEL_FOUR_THRESHOLD = 4;

    private const BULK_EVALUATION_THRESHOLD = 2000;

    public function calculateForMember(Member|string $member, ?string $fromDate = null, ?string $toDate = null): array
    {
        $memberId = $member instanceof Member ? $member->member_id : $member;
        return $this->calculateForMembers([$memberId], $fromDate, $toDate)[$memberId];
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

    public function calculateForMembers(array $memberIds, ?string $fromDate = null, ?string $toDate = null): array
    {
        $members = DB::table('members')->select(['member_id', 'sponsor_id'])->get();

        $knownMemberIds = array_flip($members->pluck('member_id')->all());
        foreach ($memberIds as $memberId) {
            if (! isset($knownMemberIds[$memberId])) {
                throw new InvalidArgumentException('The requested member does not exist.');
            }
        }

        $childrenBySponsor = $members->groupBy('sponsor_id');
        $allDownlineLookup = [];
        $downlineIdsByMember = [];
        foreach ($memberIds as $memberId) {
            $downlineIdsByMember[$memberId] = array_values(array_unique(array_merge([$memberId], $this->downlineIds($memberId, $childrenBySponsor))));
            foreach ($downlineIdsByMember[$memberId] as $downlineId) {
                $allDownlineLookup[$downlineId] = true;
            }
        }

        // Very large member sets are cheaper to aggregate once than to filter with a huge IN list.
        $bulkEvaluation = count($allDownlineLookup) > self::BULK_EVALUATION_THRESHOLD;
        $businessQuery = Investment::query()
            ->where('status', 'active')
            ->where('amount', '>=', 100);
        if (! $bulkEvaluation) {
            $businessQuery->whereIn('member_id', array_keys($allDownlineLookup));
        }

        $this->applyInvestmentDateRange($businessQuery, $fromDate, $toDate);

        $businessByMember = (clone $businessQuery)
            ->selectRaw('member_id, SUM(amount) as business')
            ->groupBy('member_id')
            ->pluck('business', 'member_id');

        $ranks = Rank::query()->where('is_active', true)->orderBy('sort_order')->get();
        $directQualifyingMemberQuery = Member::query();
        if (count($memberIds) <= self::BULK_EVALUATION_THRESHOLD) {
            $directQualifyingMemberQuery->whereIn('sponsor_id', $memberIds);
        } else {
            $directQualifyingMemberQuery->whereNotNull('sponsor_id');
        }
        $directQualifyingMemberCounts = $directQualifyingMemberQuery
            ->where('status', 'active')
            ->whereHas('investments', function ($investmentQuery) use ($fromDate, $toDate): void {
                $investmentQuery
                    ->where('status', 'active')
                    ->where('amount', '>=', 100);
                $this->applyInvestmentDateRange($investmentQuery, $fromDate, $toDate);
            })
            ->selectRaw('sponsor_id, COUNT(*) as direct_count')
            ->groupBy('sponsor_id')
            ->pluck('direct_count', 'sponsor_id');

        $results = [];
        foreach ($memberIds as $memberId) {
            $teamMemberIds = $downlineIdsByMember[$memberId];
            $fullTeamBusiness = '0.0000';
            foreach ($teamMemberIds as $teamMemberId) {
                $fullTeamBusiness = bcadd($fullTeamBusiness, (string) ($businessByMember->get($teamMemberId, '0.0000')), self::MONEY_SCALE);
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
            $directQualifyingMemberCount = (int) $directQualifyingMemberCounts->get($memberId, 0);
            $directMemberUnlockedLevels = $directQualifyingMemberCount >= self::DIRECT_MEMBER_LEVEL_FOUR_THRESHOLD
                ? self::DIRECT_MEMBER_LEVEL_FOUR_THRESHOLD
                : 0;

            $results[$memberId] = [
                'member_id' => $memberId,
                'full_team_business' => $fullTeamBusiness,
                'current_rank' => $currentRank,
                'unlocked_levels' => max($currentRank?->unlocked_levels ?? 0, $directMemberUnlockedLevels),
                'direct_qualifying_member_count' => $directQualifyingMemberCount,
                'next_rank' => $nextRank,
                'remaining_business' => $remainingBusiness,
                'rank_progress' => $progress,
                'team_member_ids' => $teamMemberIds,
            ];

        }

        return $results;
    }

    private function applyInvestmentDateRange($query, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate !== null && $fromDate !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse($fromDate, 'Asia/Kolkata')
                ->startOfDay()
                ->setTimezone('UTC'));
        }

        if ($toDate !== null && $toDate !== '') {
            $query->where('created_at', '<=', CarbonImmutable::parse($toDate, 'Asia/Kolkata')
                ->endOfDay()
                ->setTimezone('UTC'));
        }
    }

    private function qualifyingDirectMemberCount(string $memberId): int
    {
        return Member::query()
            ->where('sponsor_id', $memberId)
            ->where('status', 'active')
            ->whereHas('investments', function ($investmentQuery): void {
                $investmentQuery
                    ->where('status', 'active')
                    ->where('amount', '>=', 100);
            })
            ->count();
    }

    private function downlineIds(string $rootMemberId, Collection $childrenBySponsor): array
    {
        $downlineIds = [];
        $visited = [$rootMemberId => true];
        $pending = [$rootMemberId];

        for ($cursor = 0; $cursor < count($pending); $cursor++) {
            $sponsorId = $pending[$cursor];

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
