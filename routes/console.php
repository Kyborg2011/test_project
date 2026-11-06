<?php

declare(strict_types=1);

use App\Console\Commands\PurgeExpiredFiles;
use Illuminate\Support\Facades\Schedule;

// Expired files are deleted at most about a minute after their expiration time.
Schedule::command(PurgeExpiredFiles::class)
    ->everyMinute()
    ->withoutOverlapping();
