<?php

namespace ApiTests\Auth;

use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use App\Infrastructure\Auth\JwtService;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use ApiTests\TestCase;

class JwtRefreshTest extends TestCase
{
    public function test_refresh_with_expired_access_token_succeeds_within_session_window(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);

        // Issue a normal token
        $tokens = $jwt->issueToken($user, Request::create('/'));

        // Manually expire the access token's exp claim but keep the session valid
        $session = UserSession::where('user_id', $user->id)->where('is_revoked', false)->first();
        $this->assertNotNull($session);
        $this->assertFalse($session->isExpired()); // 7-day window still valid

        // Create an expired JWT with the same JTI
        $expiredPayload = [
            'iss' => config('jwt.issuer'),
            'sub' => $user->id,
            'iat' => time() - 3600,
            'exp' => time() - 1800, // expired 30 min ago
            'jti' => $session->jti,
            'role' => $user->role,
        ];
        $expiredToken = JWT::encode($expiredPayload, config('jwt.secret'), 'HS256');

        // Refresh should succeed because session window (7 days) is still valid
        $newTokens = $jwt->refreshToken($expiredToken, Request::create('/'));

        $this->assertArrayHasKey('access_token', $newTokens);
        $this->assertNotEquals($expiredToken, $newTokens['access_token']);
    }

    public function test_refresh_fails_when_session_window_expired(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        // Expire the session's absolute window
        UserSession::where('user_id', $user->id)->update(['expires_at' => now()->subDay()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('7-day session window');
        $jwt->refreshToken($tokens['access_token'], Request::create('/'));
    }

    public function test_refresh_via_api_endpoint_with_expired_token(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        $session = UserSession::where('user_id', $user->id)->where('is_revoked', false)->first();

        // Craft an expired token
        $expiredPayload = [
            'iss' => config('jwt.issuer'), 'sub' => $user->id,
            'iat' => time() - 3600, 'exp' => time() - 60,
            'jti' => $session->jti, 'role' => $user->role,
        ];
        $expiredToken = JWT::encode($expiredPayload, config('jwt.secret'), 'HS256');

        $this->postJson('/api/auth/refresh', [], [
            'Authorization' => "Bearer {$expiredToken}",
            'Accept' => 'application/json',
        ])->assertOk()->assertJsonStructure(['access_token']);
    }
}
