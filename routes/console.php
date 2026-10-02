<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$roiSchedule = Schedule::command(
    app()->environment(['local', 'testing', 'staging'])
        ? 'roi:generate --testing-period'
        : 'roi:generate'
)->timezone('Asia/Kolkata');

$roiSchedule->onOneServer();
$roiSchedule->everyMinute();

$levelCommissionSchedule = Schedule::command(
    app()->environment(['local', 'testing', 'staging'])
        ? 'commission:generate-level --testing-period'
        : 'commission:generate-level'
)->timezone('Asia/Kolkata');

$levelCommissionSchedule->runInBackground()->onOneServer();
$levelCommissionSchedule->everyMinute();

$rankProgressionSchedule = Schedule::command('rank:advance')
    ->timezone('Asia/Kolkata')
    ->onOneServer();

$rankProgressionSchedule->everyMinute();