<?php

namespace Tests\Unit;

use App\Services\FinancialScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class FinancialScheduleRegistrarTest extends TestCase
{
    public function test_scheduler_registers_only_the_three_production_financial_commands(): void
    {
        config(['financial.accelerated.enabled' => false]);
        $schedule = new Schedule;

        app(FinancialScheduleRegistrar::class)->register($schedule);

        $events = $schedule->events();
        $commands = array_map(fn ($event): string => $event->command, $events);
        $this->assertCount(3, $events);
        $this->assertTrue(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'roi:generate')));
        $this->assertTrue(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'commission:generate-level')));
        $this->assertTrue(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'rank:advance')));
        $this->assertFalse(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'simulation:')));
        foreach ($events as $event) {
            $this->assertSame('* * * * *', $event->expression);
            $this->assertSame('Asia/Kolkata', $event->timezone);
            $this->assertTrue($event->withoutOverlapping);
        }
    }

    public function test_scheduler_registers_only_the_coordinated_cycle_in_accelerated_mode(): void
    {
        config([
            'financial.accelerated.enabled' => true,
            'financial.accelerated.interval_minutes' => 2,
        ]);
        $schedule = new Schedule;

        app(FinancialScheduleRegistrar::class)->register($schedule);

        $events = $schedule->events();
        $this->assertCount(1, $events);
        $this->assertStringContainsString('financial:cycle', $events[0]->command);
        $this->assertSame('*/2 * * * *', $events[0]->expression);
        $this->assertSame('Asia/Kolkata', $events[0]->timezone);
        $this->assertFalse($events[0]->withoutOverlapping);
    }
}
