<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\Member;
use App\Models\Rank;
use Illuminate\Console\Command;

class RankSimulateProgression extends Command
{
    protected $signature = 'rank:simulate-progression {--member-id= : Limit demo simulation to one member}';

    protected $description = 'Create deterministic qualification data for the demo progression members.';

    private const DEMO_MEMBER_IDS = ['DEMO-101', 'DEMO-102', 'DEMO-103', 'DEMO-104'];

    public function handle(): int
    {
        $memberIds = $this->memberIds();

        foreach ($memberIds as $memberId) {
            $member = Member::query()->where('member_id', $memberId)->firstOrFail();
            $currentRank = $member->rank()->first();
            $nextRank = $this->nextRank($currentRank);

            if ($nextRank === null) {
                $this->line(sprintf(
                    '%s | Current Rank: %s | Next Rank: N/A | Required Test Data: none | Status: Complete',
                    $memberId,
                    $currentRank?->name ?? 'Unranked'
                ));

                continue;
            }

            $simulationMemberId = 'RANK-SIM-' . $memberId;
            $simulationMember = Member::query()->firstOrCreate(
                ['member_id' => $simulationMemberId],
                [
                    'sponsor_id' => $memberId,
                    'sponsor_name' => $member->member_name,
                    'member_name' => 'Simulation Child ' . $memberId,
                    'mobile_no' => '919900' . str_pad((string) substr($memberId, -3), 4, '0', STR_PAD_LEFT),
                    'email' => strtolower($simulationMemberId) . '@example.test',
                    'password' => bcrypt('simulated'),
                    'status' => 'active',
                ]
            );

            $investmentId = 'SIM-RANK-' . $memberId . '-01';
            Investment::query()->updateOrCreate(
                ['investment_id' => $investmentId],
                [
                    'member_id' => $simulationMemberId,
                    'member_name' => $simulationMember->member_name,
                    'amount' => $nextRank->required_full_team_business,
                    'status' => 'active',
                ]
            );

            $this->line(sprintf(
                '%s | Current Rank: %s | Next Rank: %s | Required Test Data: %s / %s / %s | Status: Ready',
                $memberId,
                $currentRank?->name ?? 'Unranked',
                $nextRank->name,
                $simulationMemberId,
                $investmentId,
                $nextRank->required_full_team_business
            ));
        }

        return self::SUCCESS;
    }

    private function memberIds(): array
    {
        $selected = trim((string) $this->option('member-id'));

        return $selected !== '' ? [$selected] : self::DEMO_MEMBER_IDS;
    }

    private function nextRank(?\App\Models\Rank $currentRank): ?\App\Models\Rank
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
}
