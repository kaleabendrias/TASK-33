<?php

namespace App\Domain\Policies;

use App\Domain\Models\Resource;

/**
 * Domain policy: business rules for resource allocation.
 */
class ResourcePolicy
{
    public const MAX_CAPACITY_HOURS = 2080.00; // 52 weeks × 40 hours

    public static function isWithinCapacityLimit(float $hours): bool
    {
        return $hours > 0 && $hours <= self::MAX_CAPACITY_HOURS;
    }

    public static function canBeAssigned(Resource $resource): bool
    {
        return $resource->is_available && $resource->capacity_hours > 0;
    }
}
