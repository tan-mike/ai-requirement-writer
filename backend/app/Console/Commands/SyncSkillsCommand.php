<?php

namespace App\Console\Commands;

use App\Jobs\SyncSkillsFromGoogleDriveJob;
use Illuminate\Console\Command;

class SyncSkillsCommand extends Command
{
    protected $signature = 'skills:sync';
    protected $description = 'Sync skills from Google Drive';

    public function handle(): void
    {
        $this->info('Starting skills sync...');
        SyncSkillsFromGoogleDriveJob::dispatchSync();
        $this->info('Skills sync completed.');
    }
}
