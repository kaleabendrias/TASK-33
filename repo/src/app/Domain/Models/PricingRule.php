<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'name',
        'bookable_item_id',
        'time_slot_start', 'time_slot_end',
        'days_of_week',
        'min_headcount', 'max_headcount',
        'member_tier',
        'package_code',
        'effective_from', 'effective_until',
        'adjustment_type', 'adjustment_value',
        'priority', 'is_active',
    ];

    protected $casts = [
        'effective_from'   => 'date',
        'effective_until'  => 'date',
        'adjustment_value' => 'decimal:4',
        'is_active'        => 'boolean',
        'priority'         => 'integer',
        'min_headcount'    => 'integer',
        'max_headcount'    => 'integer',
    ];

    public function bookableItem(): BelongsTo
    {
        return $this->belongsTo(BookableItem::class);
    }

    /**
     * Determine if this rule matches a pricing context.
     * The context is an associative array with keys:
     *   bookable_item_id, date (Y-m-d), start_time?, end_time?, headcount?, member_tier?, package_code?
     *
     * Matching is conjunctive: all set dimensions on the rule must match.
     */
    public function matches(array $ctx): bool
    {
        if (!$this->is_active) return false;

        // Item scope (a NULL bookable_item_id means rule applies to all items)
        if ($this->bookable_item_id !== null
            && (int) $this->bookable_item_id !== (int) ($ctx['bookable_item_id'] ?? 0)) {
            return false;
        }

        // Effective window
        $today = $ctx['date'] ?? date('Y-m-d');
        if ($this->effective_from && $today < $this->effective_from->toDateString()) return false;
        if ($this->effective_until && $today > $this->effective_until->toDateString()) return false;

        // Day of week (1=Mon..7=Sun)
        if ($this->days_of_week) {
            $dow = (int) date('N', strtotime($today));
            $days = array_map('intval', array_filter(explode(',', $this->days_of_week)));
            if (!in_array($dow, $days, true)) return false;
        }

        // Time slot — only enforce if both rule and ctx specify start/end
        if ($this->time_slot_start && !empty($ctx['start_time'])) {
            if (substr($ctx['start_time'], 0, 5) < substr((string) $this->time_slot_start, 0, 5)) return false;
        }
        if ($this->time_slot_end && !empty($ctx['end_time'])) {
            if (substr($ctx['end_time'], 0, 5) > substr((string) $this->time_slot_end, 0, 5)) return false;
        }

        // Headcount range
        $head = (int) ($ctx['headcount'] ?? 0);
        if ($this->min_headcount !== null && $head < $this->min_headcount) return false;
        if ($this->max_headcount !== null && $head > $this->max_headcount) return false;

        // Member tier (exact match if specified)
        if ($this->member_tier && ($ctx['member_tier'] ?? null) !== $this->member_tier) return false;

        // Package
        if ($this->package_code && ($ctx['package_code'] ?? null) !== $this->package_code) return false;

        return true;
    }

    /**
     * Specificity score: rules with more set dimensions win ties on priority.
     * Higher = more specific.
     */
    public function specificity(): int
    {
        $score = 0;
        if ($this->bookable_item_id !== null) $score += 8;
        if ($this->member_tier)     $score += 4;
        if ($this->package_code)    $score += 4;
        if ($this->time_slot_start || $this->time_slot_end) $score += 2;
        if ($this->days_of_week)    $score += 2;
        if ($this->min_headcount !== null || $this->max_headcount !== null) $score += 1;
        return $score;
    }

    /** Apply this rule's adjustment to a base price. */
    public function apply(float $basePrice): float
    {
        $value = (float) $this->adjustment_value;
        return match ($this->adjustment_type) {
            'fixed_price'  => round($value, 2),
            'multiplier'   => round($basePrice * $value, 2),
            'discount_pct' => round($basePrice * (1 - ($value / 100)), 2),
            default        => $basePrice,
        };
    }
}
