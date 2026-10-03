<?php

namespace App\Services;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

class ProductionDatabaseCommandGuard
{
    private const DESTRUCTIVE_COMMANDS = [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function handle(CommandStarting $event): void
    {
        if (app()->environment('production') && in_array($event->command, self::DESTRUCTIVE_COMMANDS, true)) {
            $message = 'Destructive database commands are disabled in production; use the approved recovery procedure.';
            $event->output->writeln('<error>' . $message . '</error>');

            throw new RuntimeException($message);
        }
    }
}