<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('roi:generate')
    ->dailyAt('00:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();
