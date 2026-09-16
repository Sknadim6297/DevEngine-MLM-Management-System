<?php

namespace App\Http\Controllers;

use App\Services\LevelCommissionRateResolver;
use Illuminate\Support\Collection;

class LevelCommissionController extends Controller
{
    public function statement()
    {
        return view('admin.level-commission.statement', [
            'rates' => $this->rates(),
        ]);
    }

    protected function rates(): Collection
    {
        return collect(range(1, LevelCommissionRateResolver::maxLevel()))->map(function (int $level) {
            return [
                'level' => $level,
                'percentage' => LevelCommissionRateResolver::forLevel($level),
            ];
        });
    }
}
