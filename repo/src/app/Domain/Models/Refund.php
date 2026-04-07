<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'order_id', 'original_amount', 'cancellation_fee', 'refund_amount',
        'reason', 'is_full_refund', 'staff_unavailable_override', 'status', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount'  => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
            'refund_amount'    => 'decimal:2',
            'is_full_refund'   => 'boolean',
            'staff_unavailable_override' => 'boolean',
            'processed_at'     => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
