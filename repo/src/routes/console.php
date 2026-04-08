<?php

use Illuminate\Support\Facades\Schedule;

// Auto-cancel abandoned drafts every 15 minutes so stale reservations
// don't permanently hold slot availability or rule context. The TTL
// (--max-age) defaults to 60 minutes inside the command.
Schedule::command('orders:cleanup-stale-drafts')->everyFifteenMinutes();
