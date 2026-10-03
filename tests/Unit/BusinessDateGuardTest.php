<?php

namespace Tests\Unit;

use App\Services\BusinessDateGuard;
use Carbon\CarbonImmutable;
use RuntimeException;
use Tests\TestCase;

class BusinessDateGuardTest extends TestCase
{
    public function test_production_rejects_future_kolkata_business_dates(): void
    {
        $this->app->instance('env', 'production');
        config(['database.default' => 'mysql']);

        $this->expectException(RuntimeException::class);

        app(BusinessDateGuard::class)->assertNotFuture(
            CarbonImmutable::parse('2050-01-01', 'Asia/Kolkata')
        );
    }

    public function test_isolated_sqlite_tests_can_exercise_future_business_dates(): void
    {
        $this->app->instance('env', 'testing');
        config(['database.default' => 'sqlite']);

        app(BusinessDateGuard::class)->assertNotFuture(
            CarbonImmutable::parse('2050-01-01', 'Asia/Kolkata')
        );

        $this->assertTrue(true);
    }
}
