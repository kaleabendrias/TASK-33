<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineItem extends Model
{
    protected $fillable = [
        'order_id', 'bookable_item_id', 'booking_date', 'start_time', 'end_time',
        'quantity', 'unit_price', 'tax_rate', 'line_subtotal', 'line_tax', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'booking_date'  => 'date',
            'unit_price'    => 'decimal:2',
            'tax_rate'      => 'decimal:4',
            'line_subtotal' => 'decimal:2',
            'line_tax'      => 'decimal:2',
            'line_total'    => 'decimal:2',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function bookableItem(): BelongsTo { return $this->belongsTo(BookableItem::class); }
}
