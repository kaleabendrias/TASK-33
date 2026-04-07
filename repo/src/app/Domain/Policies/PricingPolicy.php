<?php

namespace App\Domain\Policies;

use App\Domain\Models\PricingBaseline;
use Carbon\Carbon;

/**
 * Domain policy: business rules for pricing that don't belong in services or models.
 */
class PricingPolicy
{
    /**
     * A pricing baseline is considered active if today falls within its effective window.
     */
    public static function isActive(PricingBaseline $baseline): bool
    {
        $today = Carbon::today();

        if ($baseline->effective_from && $today->lt($baseline->effective_from)) {
            return false;
        }

        if ($baseline->effective_until && $today->gt($baseline->effective_until)) {
            return false;
        }

        return true;
    }

    /**
     * Minimum hourly rate floor — business invariant.
     */
    public static function meetsMinimumRate(float $hourlyRate): bool
    {
        return $hourlyRate >= 10.00;
    }
}
