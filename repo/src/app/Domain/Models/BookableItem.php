<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookableItem extends Model
{
    protected $fillable = [
        'type', 'name', 'location', 'description', 'service_area_id',
        'hourly_rate', 'daily_rate', 'unit_price', 'capacity', 'stock',
        'tax_rate', 'is_active', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'daily_rate'  => 'decimal:2',
            'unit_price'  => 'decimal:2',
            'tax_rate'    => 'decimal:4',
            'is_active'   => 'boolean',
        ];
    }

    public function serviceArea(): BelongsTo { return $this->belongsTo(ServiceArea::class); }
    public function lineItems(): HasMany { return $this->hasMany(OrderLineItem::class); }

    public function isConsumable(): bool { return $this->type === 'consumable'; }

    /**
     * Lifecycle states that hold inventory. An order in any of these
     * states has reserved (or consumed) consumable stock and has not
     * been refunded back to the pool.
     *
     * Stock decrement happens on the draft → pending transition.
     * Restore happens on a transition to `cancelled` from any of these
     * states. Refund does NOT restore — completed/refunded orders
     * represent goods that left the shelf.
     */
    public const RESERVING_STATUSES = [
        'pending', 'confirmed', 'checked_in', 'checked_out', 'completed',
    ];

    /** Check if the item has available capacity for a given date/time slot. */
    public function hasAvailability(string $date, ?string $startTime = null, ?string $endTime = null, int $qty = 1): bool
    {
        if ($this->isConsumable()) {
            // Consumables: `stock` is decremented imperatively at the
            // pending transition, so the stored value already reflects
            // committed inventory. Null stock = unlimited.
            return $this->stock === null || $this->stock >= $qty;
        }

        // Non-consumables: capacity is per-slot. The "committed" pool is
        // every line item attached to an order that hasn't been
        // terminated. Drafts ARE counted (they hold the slot until
        // submitted) — the stale-draft cleanup command auto-cancels
        // them so they don't permanently block availability.
        $booked = OrderLineItem::where('bookable_item_id', $this->id)
            ->where('booking_date', $date)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'refunded']))
            ->when($startTime && $endTime, function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)->where('end_time', '>', $startTime);
            })
            ->sum('quantity');

        return ($booked + $qty) <= $this->capacity;
    }
}
