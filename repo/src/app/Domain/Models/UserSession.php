<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'jti',
        'device_fingerprint',
        'ip_address',
        'issued_at',
        'expires_at',
        'last_active_at',
        'is_revoked',
        'revoked_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'      => 'datetime',
            'expires_at'     => 'datetime',
            'last_active_at' => 'datetime',
            'is_revoked'     => 'boolean',
            'revoked_at'     => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isInactive(): bool
    {
        $timeout = config('jwt.access_ttl', 30);
        return $this->last_active_at->addMinutes($timeout)->isPast();
    }

    public function isValid(): bool
    {
        return !$this->is_revoked && !$this->isExpired() && !$this->isInactive();
    }

    public function revoke(string $by = 'system'): void
    {
        $this->update([
            'is_revoked' => true,
            'revoked_by' => $by,
            'revoked_at' => now(),
        ]);
    }

    public function touchActivity(): bool
    {
        $this->last_active_at = now();
        return $this->save();
    }
}
