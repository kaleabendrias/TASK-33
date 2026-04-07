<?php

namespace App\Domain\Models;

use App\Domain\Traits\TracksChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use TracksChanges;

    protected $fillable = [
        'order_number', 'user_id', 'group_leader_id', 'service_area_id',
        'status', 'subtotal', 'tax_amount', 'discount_amount', 'total',
        'coupon_id', 'confirmed_at', 'checked_in_at', 'checked_out_at',
        'cancelled_at', 'cancellation_reason', 'staff_marked_unavailable', 'notes',
    ];

    protected array $trackedFields = ['status', 'total', 'staff_marked_unavailable'];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total'           => 'decimal:2',
            'confirmed_at'    => 'datetime',
            'checked_in_at'   => 'datetime',
            'checked_out_at'  => 'datetime',
            'cancelled_at'    => 'datetime',
            'staff_marked_unavailable' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function groupLeader(): BelongsTo { return $this->belongsTo(User::class, 'group_leader_id'); }
    public function serviceArea(): BelongsTo { return $this->belongsTo(ServiceArea::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function lineItems(): HasMany { return $this->hasMany(OrderLineItem::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }

    public static function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(date('Ymd')) . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /** Can the order be fully refunded (within 15 minutes of confirmation)? */
    public function isWithinFullRefundWindow(): bool
    {
        return $this->confirmed_at && $this->confirmed_at->diffInMinutes(now()) <= 15;
    }
}
