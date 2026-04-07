<?php

namespace App\Application\Services;

use App\Domain\Models\BookableItem;
use App\Domain\Models\PricingRule;
use App\Domain\Models\User;

/**
 * Deterministic pricing resolver.
 *
 * Given a base price and a context (item, slot, headcount, member tier, package),
 * picks exactly one PricingRule deterministically and applies it. Ordering:
 *   1. priority ASCending (lower number = higher precedence)
 *   2. specificity DESCending (more set dimensions wins ties)
 *   3. id ASCending (oldest rule wins remaining ties — fully deterministic)
 *
 * If no rule matches, the base price is returned unchanged.
 */
class PricingResolver
{
    /**
     * Resolve the unit price for a single line item.
     *
     * $context keys:
     *   bookable_item_id (int, required)
     *   date             (Y-m-d, required)
     *   start_time       (HH:MM, optional)
     *   end_time         (HH:MM, optional)
     *   headcount        (int, optional, default 1)
     *   member_tier      (string, optional)
     *   package_code     (string, optional)
     *
     * Returns ['unit_price' => float, 'rule' => ?PricingRule]
     */
    public function resolveUnitPrice(BookableItem $item, float $basePrice, array $context): array
    {
        $context['bookable_item_id'] = $item->id;
        $context['date'] = $context['date'] ?? date('Y-m-d');
        $context['headcount'] = $context['headcount'] ?? 1;

        $rules = PricingRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($item) {
                $q->whereNull('bookable_item_id')->orWhere('bookable_item_id', $item->id);
            })
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn (PricingRule $r) => $r->matches($context))
            ->values();

        if ($rules->isEmpty()) {
            return ['unit_price' => round($basePrice, 2), 'rule' => null];
        }

        // Sort: priority asc, specificity desc, id asc — fully deterministic
        $sorted = $rules->sort(function (PricingRule $a, PricingRule $b) {
            if ($a->priority !== $b->priority) return $a->priority <=> $b->priority;
            $sa = $a->specificity();
            $sb = $b->specificity();
            if ($sa !== $sb) return $sb <=> $sa;
            return $a->id <=> $b->id;
        })->values();

        $winner = $sorted->first();
        return [
            'unit_price' => $winner->apply($basePrice),
            'rule'       => $winner,
        ];
    }

    /** Convenience: resolve member tier from a user (defaults to 'standard'). */
    public static function tierForUser(?User $user): string
    {
        if (!$user) return 'standard';
        return $user->member_tier ?: 'standard';
    }
}
