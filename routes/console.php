<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$roiSchedule = Schedule::command(
    app()->environment(['local', 'testing'])
        ? 'roi:generate --testing-period'
        : 'roi:generate'
)->timezone('Asia/Kolkata')->withoutOverlapping();

if (app()->environment(['local', 'testing'])) {
    $roiSchedule->everyTwoMinutes();
} else {
    $roiSchedule->dailyAt('00:00');
}
