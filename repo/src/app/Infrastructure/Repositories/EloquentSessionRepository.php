<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\SessionRepositoryInterface;
use App\Domain\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;

class EloquentSessionRepository implements SessionRepositoryInterface
{
    public function createSession(
        int $userId,
        string $jti,
        string $expiresAt,
        ?string $ip = null,
        ?string $deviceFingerprint = null,
    ): UserSession {
        return UserSession::create([
            'user_id'            => $userId,
            'jti'                => $jti,
            'device_fingerprint' => $deviceFingerprint,
            'ip_address'         => $ip,
            'issued_at'          => now(),
            'expires_at'         => $expiresAt,
            'last_active_at'     => now(),
        ]);
    }

    public function findByJti(string $jti): ?UserSession
    {
        return UserSession::where('jti', $jti)->first();
    }

    public function activeSessions(int $userId): Collection
    {
        return UserSession::where('user_id', $userId)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('last_active_at')
            ->get();
    }

    public function revokeSession(string $jti, string $by = 'user'): void
    {
        $session = $this->findByJti($jti);
        $session?->revoke($by);
    }

    public function revokeAllForUser(int $userId, string $by = 'admin'): int
    {
        return UserSession::where('user_id', $userId)
            ->where('is_revoked', false)
            ->update([
                'is_revoked' => true,
                'revoked_by' => $by,
                'revoked_at' => now(),
            ]);
    }

    public function revokeOldestIfOverLimit(int $userId, int $maxSessions): void
    {
        $active = $this->activeSessions($userId);

        // We need room for one new session, so limit is maxSessions - 1
        if ($active->count() >= $maxSessions) {
            $toRevoke = $active->sortBy('last_active_at')->take($active->count() - $maxSessions + 1);
            foreach ($toRevoke as $session) {
                $session->revoke('system');
            }
        }
    }
}
