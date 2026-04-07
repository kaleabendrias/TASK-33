<?php

namespace App\Domain\Models;

use App\Domain\Traits\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory;
    use EncryptsSensitiveFields;

    protected $fillable = [
        'username',
        'password',
        'full_name',
        'email_encrypted',
        'phone_encrypted',
        'email_hash',
        'phone_hash',
        'role',
        'member_tier',
        'is_active',
        'password_changed_at',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'email_encrypted',
        'phone_encrypted',
        // Hash columns are deterministic SHA-256 of plaintext PII; they enable
        // server-side lookups but must NEVER reach the client where rainbow-table
        // attacks would defeat the encryption-at-rest scheme.
        'email_hash',
        'phone_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /** Encrypted field definitions for the EncryptsSensitiveFields trait. */
    protected array $encryptedFields = ['email_encrypted', 'phone_encrypted'];

    /** Hash fields: field_name => hash_column */
    protected array $hashIndexFields = [
        'email_encrypted' => 'email_hash',
        'phone_encrypted' => 'phone_hash',
    ];

    // ── Mutators ────────────────────────────────────────────────────

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function verifyPassword(string $plain): bool
    {
        return Hash::check($plain, $this->password);
    }

    // ── Relationships ───────────────────────────────────────────────

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->sessions()
            ->where('is_revoked', false)
            ->where('expires_at', '>', now());
    }

    // ── Role helpers ────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAtLeast(string $role): bool
    {
        $hierarchy = ['user' => 1, 'staff' => 2, 'group-leader' => 3, 'admin' => 4];
        return ($hierarchy[$this->role] ?? 0) >= ($hierarchy[$role] ?? 999);
    }
}
