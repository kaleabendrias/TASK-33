<?php

namespace App\Infrastructure\Auth;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Contracts\SessionRepositoryInterface;
use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $accessTtl;
    private int $refreshTtl;
    private int $maxSessions;
    private string $issuer;

    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly AuditLogRepositoryInterface $audit,
    ) {
        $this->secret      = config('jwt.secret');
        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET environment variable is not set.');
        }
        $this->algorithm   = config('jwt.algorithm', 'HS256');
        $this->accessTtl   = config('jwt.access_ttl', 30);
        $this->refreshTtl  = config('jwt.refresh_ttl', 10080);
        $this->maxSessions = config('jwt.max_sessions', 2);
        $this->issuer      = config('jwt.issuer', 'http://localhost:8080');
    }

    /**
     * Issue a new JWT access token + create a session record.
     * Enforces max concurrent session limit by evicting the oldest.
     */
    public function issueToken(User $user, Request $request): array
    {
        // Enforce session cap
        $this->sessions->revokeOldestIfOverLimit($user->id, $this->maxSessions);

        $jti = bin2hex(random_bytes(32));
        $now = time();
        $accessExp  = $now + ($this->accessTtl * 60);
        $refreshExp = $now + ($this->refreshTtl * 60);

        $payload = [
            'iss'  => $this->issuer,
            'sub'  => $user->id,
            'iat'  => $now,
            'exp'  => $accessExp,
            'jti'  => $jti,
            'role' => $user->role,
        ];

        $token = JWT::encode($payload, $this->secret, $this->algorithm);

        $this->sessions->createSession(
            userId: $user->id,
            jti: $jti,
            expiresAt: date('Y-m-d H:i:s', $refreshExp),
            ip: $request->ip(),
            deviceFingerprint: $request->header('User-Agent'),
        );

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->accessTtl * 60,
            'refresh_until' => date('c', $refreshExp),
        ];
    }

    /**
     * Decode and validate a JWT. Returns [payload, session] or throws.
     */
    public function validateToken(string $token): array
    {
        $payload = JWT::decode($token, new Key($this->secret, $this->algorithm));

        $session = $this->sessions->findByJti($payload->jti);

        if (!$session) {
            throw new \RuntimeException('Session not found.');
        }

        if ($session->is_revoked) {
            throw new \RuntimeException('Token has been revoked.');
        }

        if ($session->isExpired()) {
            throw new \RuntimeException('Session has expired (7-day maximum).');
        }

        if ($session->isInactive()) {
            throw new \RuntimeException('Session timed out due to inactivity.');
        }

        // Touch last_active_at for sliding inactivity window
        $session->last_active_at = now();
        $session->save();

        return [(array) $payload, $session];
    }

    /**
     * Refresh: revoke the old token and issue a new one if within the 7-day window.
     */
    public function refreshToken(string $oldToken, Request $request): array
    {
        // Decode the token — allow expired access tokens since we validate
        // against the session's 7-day absolute window, not the 30-min access TTL.
        try {
            $payload = JWT::decode($oldToken, new Key($this->secret, $this->algorithm));
        } catch (ExpiredException $e) {
            // Token is expired but structurally valid — extract payload from the exception
            $payload = $e->getPayload();
        }

        $session = $this->sessions->findByJti($payload->jti);

        if (!$session || $session->is_revoked) {
            throw new \RuntimeException('Cannot refresh: session invalid.');
        }

        if ($session->isExpired()) {
            throw new \RuntimeException('Cannot refresh: 7-day session window has expired. Please log in again.');
        }

        // Revoke the old session
        $session->revoke('refresh');

        // Load user
        $user = $session->user;
        if (!$user || !$user->is_active) {
            throw new \RuntimeException('User account is disabled.');
        }

        // Issue new token inheriting the same absolute expiry
        $jti = bin2hex(random_bytes(32));
        $now = time();
        $accessExp = $now + ($this->accessTtl * 60);

        $newPayload = [
            'iss'  => $this->issuer,
            'sub'  => $user->id,
            'iat'  => $now,
            'exp'  => $accessExp,
            'jti'  => $jti,
            'role' => $user->role,
        ];

        $token = JWT::encode($newPayload, $this->secret, $this->algorithm);

        // New session inherits the original absolute expiry
        $this->sessions->createSession(
            userId: $user->id,
            jti: $jti,
            expiresAt: $session->expires_at->toDateTimeString(),
            ip: $request->ip(),
            deviceFingerprint: $request->header('User-Agent'),
        );

        return [
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'expires_in'    => $this->accessTtl * 60,
            'refresh_until' => $session->expires_at->toIso8601String(),
        ];
    }

    /**
     * Revoke a specific session by JTI.
     */
    public function revokeByJti(string $jti, string $by = 'user'): void
    {
        $this->sessions->revokeSession($jti, $by);
    }

    /**
     * Admin: revoke all sessions for a user.
     */
    public function revokeAllForUser(int $userId, string $by = 'admin'): int
    {
        return $this->sessions->revokeAllForUser($userId, $by);
    }
}
