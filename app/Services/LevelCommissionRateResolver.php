<?php

namespace App\Services;

class LevelCommissionRateResolver
{
    public static function forLevel(int $level): string
    {
        $configuredRates = config('level_commission.rates', []);

        $rate = $configuredRates[$level]
            ?? ($level <= 20 ? ($configuredRates['5-20'] ?? null) : ($configuredRates['21-32'] ?? null));

        return (string) ($rate ?? '0');
    }

    public static function forLevelAsFloat(int $level): float
    {
        return (float) self::forLevel($level);
    }

    public static function maxLevel(): int
    {
        return 32;
    }
}
