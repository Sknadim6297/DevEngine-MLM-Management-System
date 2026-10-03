<?php

namespace Tests\Unit;

use App\Services\ProductionDatabaseCommandGuard;
use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ProductionDatabaseCommandGuardTest extends TestCase
{
    public function test_production_blocks_destructive_database_commands(): void
    {
        $this->app->instance('env', 'production');
        $event = new CommandStarting('migrate:fresh', new ArrayInput([]), new BufferedOutput());

        $this->expectException(RuntimeException::class);
        app(ProductionDatabaseCommandGuard::class)->handle($event);
    }

    public function test_production_allows_normal_migrations(): void
    {
        $this->app->instance('env', 'production');
        $event = new CommandStarting('migrate', new ArrayInput([]), new BufferedOutput());

        app(ProductionDatabaseCommandGuard::class)->handle($event);

        $this->assertTrue(true);
    }
}