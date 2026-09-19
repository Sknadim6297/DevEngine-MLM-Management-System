<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function direct(Request $request): View
    {
        $team = $this->buildTeam($request);

        return view('member.team.index', [
            'member' => $team['member'],
            'directMembers' => collect($team['members'])->where('level', 1)->values(),
            'wholeTeam' => collect($team['members']),
            'view' => 'direct',
        ]);
    }

    public function whole(Request $request): View
    {
        $team = $this->buildTeam($request);

        return view('member.team.index', [
            'member' => $team['member'],
            'directMembers' => collect($team['members'])->where('level', 1)->values(),
            'wholeTeam' => collect($team['members']),
            'view' => 'whole',
        ]);
    }

    private function buildTeam(Request $request): array
    {
        $root = Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
        $members = Member::query()
            ->select(['member_id', 'member_name', 'sponsor_id', 'email', 'mobile_no', 'status', 'created_at'])
            ->orderBy('created_at')
            ->get();

        $childrenBySponsor = $members->groupBy('sponsor_id');
        $queue = [[$root->member_id, 0]];
        $visited = [$root->member_id => true];
        $team = [];

        while ($queue !== []) {
            [$sponsorId, $parentLevel] = array_shift($queue);
            $level = $parentLevel + 1;

            foreach ($childrenBySponsor->get($sponsorId, collect()) as $child) {
                if (isset($visited[$child->member_id])) {
                    continue;
                }

                $visited[$child->member_id] = true;
                $team[] = [
                    'level' => $level,
                    'member_id' => $child->member_id,
                    'member_name' => $child->member_name,
                    'sponsor_id' => $child->sponsor_id,
                    'email' => $child->email,
                    'mobile_no' => $child->mobile_no,
                    'status' => $child->status,
                    'created_at' => $child->created_at,
                ];
                $queue[] = [$child->member_id, $level];
            }
        }

        return [
            'member' => $root,
            'members' => $team,
        ];
    }
}
