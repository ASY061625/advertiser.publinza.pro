<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('scout:sync-index-settings')->daily();
Schedule::command('auth:clear-resets')->daily();
