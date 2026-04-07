<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupLeaderAssignment extends Model
{
    protected $fillable = ['user_id', 'service_area_id', 'location', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function serviceArea(): BelongsTo { return $this->belongsTo(ServiceArea::class); }
}
