<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pekerjaan:update-status')->dailyAt('00:01');
Schedule::command('notifikasi:deadline')->dailyAt('07:00');
Schedule::command('notifikasi:laporan-harian masuk')->dailyAt('06:30')->weekdays();
Schedule::command('notifikasi:laporan-harian pulang')->dailyAt('15:00')->weekdays();
Schedule::command('notifikasi:termin-pending')->dailyAt('08:00');
Schedule::command('notifikasi:weekly-digest')->mondays()->at('07:00');
