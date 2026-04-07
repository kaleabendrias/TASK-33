<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'min_order_amount',
        'max_uses', 'used_count', 'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value'   => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'valid_from'       => 'date',
            'valid_until'      => 'date',
            'is_active'        => 'boolean',
        ];
    }

    public function isValid(float $orderSubtotal): bool|string
    {
        if (!$this->is_active) return 'Coupon is inactive.';
        if ($this->valid_from->isFuture()) return 'Coupon is not yet valid.';
        if ($this->valid_until && $this->valid_until->isPast()) return 'Coupon has expired.';
        if ($this->max_uses && $this->used_count >= $this->max_uses) return 'Coupon usage limit reached.';
        if ($orderSubtotal < (float) $this->min_order_amount) return "Minimum order amount is \${$this->min_order_amount}.";
        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        return $this->discount_type === 'percentage'
            ? round($subtotal * (float) $this->discount_value / 100, 2)
            : min((float) $this->discount_value, $subtotal);
    }
}
