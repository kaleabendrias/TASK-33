<?php

namespace App\Domain\Models;

use App\Domain\Traits\HasAttachments;
use App\Domain\Traits\HasStatusLifecycle;
use App\Domain\Traits\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    use HasFactory;
    use HasStatusLifecycle;
    use TracksChanges;
    use HasAttachments;

    protected $fillable = [
        'parent_id',
        'name',
        'service_area_id',
        'role_id',
        'capacity_hours',
        'is_available',
        'status',
    ];

    protected $attributes = [
        'status' => 'available',
    ];

    protected array $trackedFields = [
        'name', 'service_area_id', 'role_id', 'capacity_hours', 'is_available', 'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity_hours' => 'decimal:2',
            'is_available'   => 'boolean',
        ];
    }

    // ── Status lifecycle ────────────────────────────────────────────

    public static function allowedTransitions(): array
    {
        return [
            'available'      => ['reserved', 'maintenance', 'decommissioned'],
            'reserved'       => ['in_use', 'available', 'maintenance'],
            'in_use'         => ['available', 'maintenance'],
            'maintenance'    => ['available', 'decommissioned'],
            'decommissioned' => ['available'],
        ];
    }

    // ── Relationships ───────────────────────────────────────────────

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
