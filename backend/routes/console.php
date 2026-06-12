<?php

use App\Jobs\SyncContextsFromGoogleDriveJob;
use App\Jobs\SyncSkillsFromGoogleDriveJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncSkillsFromGoogleDriveJob)->everyThirtyMinutes();
Schedule::job(new SyncContextsFromGoogleDriveJob)->everyThirtyMinutes();
