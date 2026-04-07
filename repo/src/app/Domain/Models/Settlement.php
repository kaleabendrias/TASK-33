<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $fillable = [
        'reference', 'period_start', 'period_end', 'gross_amount', 'refund_total',
        'net_amount', 'order_count', 'refund_count', 'status', 'cycle_type',
        'finalized_at', 'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date',
            'gross_amount' => 'decimal:2', 'refund_total' => 'decimal:2', 'net_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function finalizer(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by'); }
    public function commissions(): HasMany { return $this->hasMany(Commission::class); }
}
