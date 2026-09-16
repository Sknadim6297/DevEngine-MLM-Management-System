<?php

namespace App\Services;

use App\Models\LevelCommission;

class LevelCommissionRateResolver
{
    public static function forLevel(int $level): string
    {
        return (string) (LevelCommission::query()
            ->active()
            ->where('level', $level)
            ->value('percentage') ?? '0');
    }

    public static function forLevelAsFloat(int $level): float
    {
        return (float) self::forLevel($level);
    }

    public static function maxLevel(): int
    {
        return (int) (LevelCommission::query()->max('level') ?? 0);
    }
}
