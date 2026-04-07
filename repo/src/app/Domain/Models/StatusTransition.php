<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StatusTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transitionable_type',
        'transitionable_id',
        'from_status',
        'to_status',
        'reason',
        'transitioned_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transitionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }
}
