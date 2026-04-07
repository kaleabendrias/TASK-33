<?php

namespace App\Domain\Contracts;

use App\Domain\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;

interface SessionRepositoryInterface
{
    public function createSession(int $userId, string $jti, string $expiresAt, ?string $ip = null, ?string $deviceFingerprint = null): UserSession;
    public function findByJti(string $jti): ?UserSession;
    public function activeSessions(int $userId): Collection;
    public function revokeSession(string $jti, string $by = 'user'): void;
    public function revokeAllForUser(int $userId, string $by = 'admin'): int;
    public function revokeOldestIfOverLimit(int $userId, int $maxSessions): void;
}
