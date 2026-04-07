<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'group_leader_id', 'settlement_id', 'cycle_start', 'cycle_end', 'cycle_type',
        'attributed_revenue', 'commission_rate', 'commission_amount', 'order_count',
        'status', 'hold_until',
    ];

    protected function casts(): array
    {
        return [
            'cycle_start'        => 'date',
            'cycle_end'          => 'date',
            'attributed_revenue' => 'decimal:2',
            'commission_rate'    => 'decimal:4',
            'commission_amount'  => 'decimal:2',
            'hold_until'         => 'datetime',
        ];
    }

    public function groupLeader(): BelongsTo { return $this->belongsTo(User::class, 'group_leader_id'); }
    public function settlement(): BelongsTo { return $this->belongsTo(Settlement::class); }
}
