<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('scout:sync-index-settings')->daily();
Schedule::command('auth:clear-resets')->daily();

/*
 * The catch-up half of the fifteen-minute email rule. Every minute, because the
 * window is fifteen and a summary that is due should not wait for a sixteenth.
 */
Schedule::command('notifications:send-digests')->everyMinute()->withoutOverlapping();

/*
 * Deadlines, low balances and cart price drift — the notifications that come
 * from time passing rather than from something happening. Daily, because every
 * one of them is about a state that persists, and each is deduped against what
 * has already been sent.
 */
Schedule::command('notifications:scan')->dailyAt('08:00');

// Monday morning, and only to accounts with something to summarise.
Schedule::command('notifications:weekly-summary')->weeklyOn(1, '09:00');
