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

    public function levelView(Request $request)
    {
        return view('admin.genealogy.level-view');
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
