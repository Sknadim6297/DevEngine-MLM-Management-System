<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

class BusinessDateGuard
{
    public function assertNotFuture(CarbonImmutable $businessDate): void
    {
        if (app()->environment('testing') && config('database.default') === 'sqlite') {
            return;
        }

        if (config('financial.accelerated.enabled') && ! app()->environment('production')) {
            return;
        }

        if ($businessDate->setTimezone('Asia/Kolkata')->startOfDay()->greaterThan(CarbonImmutable::now('Asia/Kolkata')->startOfDay())) {
            throw new RuntimeException('Future business dates are not accepted by production financial commands.');
        }
    }
}