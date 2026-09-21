<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GenealogyController extends Controller
{
    public function treeView(Request $request): View
    {
        $member = $this->currentMember($request);

        return view('member.genealogy.tree-view', [
            'member' => $member,
            'tree' => [$this->buildTreeNode($member)],
            'selectedMemberId' => $member->member_id,
            'memberNotFound' => false,
        ]);
    }

    public function levelView(Request $request): View
    {
        return view('member.genealogy.level-view', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function levelMembers(Request $request): JsonResponse
    {
        $member = $this->currentMember($request);
        $levelFilter = trim((string) $request->query('level', ''));

        if ($levelFilter !== '' && (! ctype_digit($levelFilter) || (int) $levelFilter < 1 || (int) $levelFilter > 32)) {
            return response()->json(['message' => 'Level must be a number between 1 and 32.'], 422);
        }

        $results = [];
        $visited = [$member->member_id => true];
        $currentLevelMembers = [$member];
        $level = 1;

        while ($currentLevelMembers !== [] && $level <= 32) {
            $nextLevelMembers = [];

            foreach ($currentLevelMembers as $parent) {
                $children = Member::where('sponsor_id', $parent->member_id)
                    ->orderBy('member_name')
                    ->get();

                foreach ($children as $child) {
                    if (isset($visited[$child->member_id])) {
                        continue;
                    }

                    $visited[$child->member_id] = true;
                    $nextLevelMembers[] = $child;

                    if ($levelFilter === '' || (int) $levelFilter === $level) {
                        $results[] = [
                            'level' => $level,
                            'member_id' => $child->member_id,
                            'member_name' => $child->member_name,
                            'sponsor_id' => $child->sponsor_id,
                            'status' => $child->status,
                        ];
                    }
                }
            }

            $currentLevelMembers = $nextLevelMembers;
            $level++;
        }

        return response()->json(['data' => $results]);
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }

    private function buildTreeNode(Member $member, array &$visited = []): array
    {
        if (isset($visited[$member->member_id])) {
            return [
                'id' => $member->member_id,
                'name' => $member->member_name,
                'status' => $member->status,
                'children' => [],
            ];
        }

        $visited[$member->member_id] = true;
        $children = Member::query()
            ->where('sponsor_id', $member->member_id)
            ->orderBy('member_name')
            ->get();

        return [
            'id' => $member->member_id,
            'name' => $member->member_name,
            'status' => $member->status,
            'children' => $children->map(fn (Member $child) => $this->buildTreeNode($child, $visited))->values()->all(),
        ];
    }
}
