<?php

namespace App\Http\Controllers;

use App\Models\Rank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RankController extends Controller
{
    public function index(): View
    {
        return view('admin.ranks.index', [
            'ranks' => Rank::query()->orderBy('sort_order')->get(),
            'rank' => new Rank(['is_active' => true, 'unlocked_levels' => 1, 'sort_order' => 1]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Rank::create($this->validated($request));

        return redirect()->route('admin.ranks.index')->with('success', 'Rank created successfully.');
    }

    public function edit(Rank $rank): View
    {
        return view('admin.ranks.index', [
            'ranks' => Rank::query()->orderBy('sort_order')->get(),
            'rank' => $rank,
        ]);
    }

    public function update(Request $request, Rank $rank): RedirectResponse
    {
        DB::transaction(function () use ($request, $rank): void {
            $rank->update($this->validated($request, $rank));
        });

        return redirect()->route('admin.ranks.index')->with('success', 'Rank updated successfully.');
    }

    public function destroy(Rank $rank): RedirectResponse
    {
        if ($rank->members()->exists()) {
            return redirect()->route('admin.ranks.index')->withErrors([
                'rank' => 'This rank cannot be deleted because it is currently assigned to one or more members.',
            ]);
        }

        $rank->delete();

        return redirect()->route('admin.ranks.index')->with('success', 'Rank deleted successfully.');
    }

    private function validated(Request $request, ?Rank $rank = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('ranks', 'name')->ignore($rank?->id)],
            'required_full_team_business' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'unlocked_levels' => ['required', 'integer', 'min:1', 'max:32'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:255', Rule::unique('ranks', 'sort_order')->ignore($rank?->id)],
        ]);
    }
}
