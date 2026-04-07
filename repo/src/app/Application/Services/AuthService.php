<?php

namespace App\Application\Services;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Contracts\UserRepositoryInterface;
use App\Domain\Policies\PasswordPolicy;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly JwtService $jwt,
        private readonly AuditLogRepositoryInterface $audit,
    ) {}

    public function login(string $username, string $password, Request $request): array
    {
        $user = $this->users->findByUsername($username);

        if (!$user || !$user->verifyPassword($password)) {
            $this->audit->log('login_failed', 'User', null, null, null, [
                'username' => $username,
                'reason'   => 'invalid_credentials',
            ]);
            throw ValidationException::withMessages([
                'credentials' => 'Invalid username or password.',
            ]);
        }

        if (!$user->is_active) {
            $this->audit->log('login_failed', 'User', $user->id, null, null, [
                'reason' => 'account_disabled',
            ]);
            // Same generic message — do not reveal account status
            throw ValidationException::withMessages([
                'credentials' => 'Invalid username or password.',
            ]);
        }

        $tokens = $this->jwt->issueToken($user, $request);

        $this->audit->log('login', 'User', $user->id);

        if ($user->must_change_password) {
            $tokens['must_change_password'] = true;
        }

        return $tokens;
    }

    public function refresh(string $token, Request $request): array
    {
        $tokens = $this->jwt->refreshToken($token, $request);

        $this->audit->log('token_refresh', 'User', null, null, null, [
            'ip' => $request->ip(),
        ]);

        return $tokens;
    }

    public function logout(Request $request): void
    {
        $session = $request->attributes->get('auth_session');
        $user = $request->attributes->get('auth_user');

        if ($session) {
            $session->revoke('user');
        }

        $this->audit->log('logout', 'User', $user?->id);
    }

    /**
     * Admin: revoke all tokens for a user.
     */
    public function adminRevokeTokens(int $userId): int
    {
        $count = $this->jwt->revokeAllForUser($userId, 'admin');

        $this->audit->log('admin_revoke_tokens', 'User', $userId, null, null, [
            'sessions_revoked' => $count,
        ]);

        return $count;
    }

    /**
     * Admin: offline password reset (no email required).
     */
    public function adminResetPassword(int $userId, string $newPassword): void
    {
        $errors = PasswordPolicy::validate($newPassword);
        if ($errors) {
            throw ValidationException::withMessages(['password' => $errors]);
        }

        $user = $this->users->findOrFail($userId);
        $oldHash = $user->password;

        $this->users->update($user, [
            'password'              => $newPassword,
            'password_changed_at'   => now(),
            'must_change_password'  => false,
        ]);

        // Revoke all existing sessions after password change
        $this->jwt->revokeAllForUser($userId, 'password_reset');

        $this->audit->log('admin_password_reset', 'User', $userId, [
            'password_hash' => '***',
        ], [
            'password_hash' => '***',
        ]);
    }
}
