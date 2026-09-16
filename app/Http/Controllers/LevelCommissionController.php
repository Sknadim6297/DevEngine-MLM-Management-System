<?php

namespace App\Http\Controllers;

use App\Models\LevelCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class LevelCommissionController extends Controller
{
    public function statement()
    {
        return view('admin.level-commission.statement', [
            'rates' => $this->rates(),
        ]);
    }

    public function update(Request $request, LevelCommission $levelCommission)
    {
        $validated = $request->validate([
            'percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
        ]);

        DB::transaction(function () use ($validated, $levelCommission) {
            $commission = LevelCommission::query()
                ->lockForUpdate()
                ->findOrFail($levelCommission->id);

            $commission->update([
                'percentage' => $validated['percentage'],
            ]);
        });

        return redirect()
            ->route('admin.level-commission.statement')
            ->with('success', 'Level commission percentage updated successfully.');
    }

    protected function rates(): Collection
    {
        return LevelCommission::query()
            ->active()
            ->orderBy('level')
            ->get()
            ->map(function (LevelCommission $commission) {
            return [
                'id' => $commission->id,
                'level' => $commission->level,
                'percentage' => rtrim(rtrim((string) $commission->percentage, '0'), '.') ?: '0',
            ];
            });
    }
}
