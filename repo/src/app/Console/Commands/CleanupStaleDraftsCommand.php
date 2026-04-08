<?php

namespace App\Console\Commands;

use App\Application\Services\BookingService;
use Illuminate\Console\Command;

/**
 * Auto-cancel draft orders older than the configured TTL.
 *
 * Drafts that the customer never submits hold onto pricing context
 * (and, for non-consumables, slot capacity once they've moved past
 * draft) so they need an upper bound. Run on a schedule.
 *
 *   php artisan orders:cleanup-stale-drafts --max-age=60
 */
class CleanupStaleDraftsCommand extends Command
{
    protected $signature = 'orders:cleanup-stale-drafts {--max-age=60 : Maximum draft age in minutes}';
    protected $description = 'Auto-cancel draft orders older than the configured TTL.';

    public function handle(BookingService $booking): int
    {
        $maxAge = (int) $this->option('max-age');
        $count = $booking->cleanupStaleDrafts($maxAge);
        $this->info("Cleaned up {$count} stale draft order(s) older than {$maxAge} minute(s).");
        return self::SUCCESS;
    }
}
