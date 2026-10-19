<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Nightly backup of the database and photos into ./backups (see scripts/README.md).
Schedule::exec('bash '.base_path('scripts/backup.sh'))
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onSuccess(fn () => Log::info('Nightly backup finished.'))
    ->onFailure(fn () => Log::error('Nightly backup FAILED. Run scripts/backup.sh by hand to see why.'));
