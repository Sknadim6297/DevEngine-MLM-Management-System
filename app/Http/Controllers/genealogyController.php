<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

class genealogyController extends Controller
{
    public function index(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));

        if ($memberId !== '') {
            $selectedMember = Member::where('member_id', $memberId)->first();

            if (! $selectedMember) {
                return view('admin.genealogy.tree-view', [
                    'tree' => [],
                    'selectedMemberId' => $memberId,
                    'memberNotFound' => true,
                ]);
            }

            $tree = [$this->buildTreeNode($selectedMember)];
        } else {
            $tree = $this->buildRootTree();
        }

        return view('admin.genealogy.tree-view', [
            'tree' => $tree,
            'selectedMemberId' => $memberId,
            'memberNotFound' => false,
        ]);
    }

    public function searchMembers(Request $request)
    {
        $term = trim((string) $request->query('member_id', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $results = Member::query()
            ->where(function ($query) use ($term) {
                $query->where('member_id', 'like', '%' . $term . '%')
                    ->orWhere('member_name', 'like', '%' . $term . '%');
            })
            ->select(['member_id', 'member_name', 'status'])
            ->orderBy('member_id')
            ->limit(10)
            ->get()
            ->map(fn (Member $member) => [
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'status' => $member->status,
            ])
            ->values()
            ->all();

        return response()->json($results);
    }

    public function levelView(Request $request)
    {
        return view('admin.genealogy.level-view');
    }

    public function levelMembers(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $levelFilter = trim((string) $request->query('level', ''));

        if ($memberId === '') {
            return response()->json(['message' => 'Member ID is required.'], 422);
        }

        $root = Member::where('member_id', $memberId)->first();

        if (! $root) {
            return response()->json(['message' => 'The selected member id is invalid.'], 404);
        }

        if ($levelFilter !== '' && (! ctype_digit($levelFilter) || (int) $levelFilter < 1 || (int) $levelFilter > 32)) {
            return response()->json(['message' => 'Level must be a number between 1 and 32.'], 422);
        }

        $results = [];
        $visited = [$root->member_id => true];
        $currentLevelMembers = [$root];
        $level = 1;

        while (! empty($currentLevelMembers) && $level <= 32) {
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

    protected function buildRootTree(): array
    {
        $memberIds = Member::query()->pluck('member_id')->all();

        $roots = Member::query()
            ->where(function ($query) {
                $query->whereNull('sponsor_id')
                    ->orWhere('sponsor_id', '')
                    ->orWhere('sponsor_id', 'ST666666');
            })
            ->orWhereNotIn('sponsor_id', $memberIds)
            ->orderBy('member_name')
            ->get();

        if ($roots->isEmpty()) {
            $roots = Member::query()->orderBy('member_name')->get();
        }

        $tree = [];
        $visited = [];

        foreach ($roots as $root) {
            $tree[] = $this->buildTreeNode($root, $visited);
        }

        return $tree;
    }

    protected function buildTreeNode(Member $member, array &$visited = []): array
    {
        if (isset($visited[$member->member_id])) {
            return [
                'id' => $member->member_id,
                'name' => $member->member_name,
                'status' => $member->status ?? null,
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
            'status' => $member->status ?? null,
            'children' => $children->map(function ($child) use (&$visited) {
                return $this->buildTreeNode($child, $visited);
            })->values()->all(),
        ];
    }
}
