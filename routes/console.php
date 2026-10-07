<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* Catch any RankYak article a webhook missed, and report posts that have just gone live. Needs the scheduler (cron) running. */
Schedule::command('rankyak:sync')->hourly()->withoutOverlapping()->runInBackground();
