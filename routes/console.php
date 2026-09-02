<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prix du gasoil : actualisation quotidienne (nécessite un cron cPanel appelant `artisan schedule:run`).
Schedule::command('gasoil:actualiser')->dailyAt('06:00');
