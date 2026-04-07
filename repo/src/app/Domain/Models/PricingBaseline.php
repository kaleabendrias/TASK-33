<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingBaseline extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_area_id',
        'role_id',
        'hourly_rate',
        'currency',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
