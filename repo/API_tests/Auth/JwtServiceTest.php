<?php

namespace ApiTests\Auth;

use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use ApiTests\TestCase;

class JwtServiceTest extends TestCase
{
    private JwtService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = app(JwtService::class);
    }

    public function test_issue_token_creates_session(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertCount(1, UserSession::where('user_id', $user->id)->get());
    }

    public function test_validate_token_success(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        [$payload, $session] = $this->jwt->validateToken($tokens['access_token']);
        $this->assertEquals($user->id, $payload['sub']);
    }

    public function test_validate_revoked_token_throws(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        // Revoke all sessions
        UserSession::where('user_id', $user->id)->update(['is_revoked' => true]);
        $this->expectException(\RuntimeException::class);
        $this->jwt->validateToken($tokens['access_token']);
    }

    public function test_validate_expired_session_throws(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        UserSession::where('user_id', $user->id)->update(['expires_at' => now()->subHour()]);
        $this->expectException(\RuntimeException::class);
        $this->jwt->validateToken($tokens['access_token']);
    }

    public function test_validate_inactive_session_throws(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        UserSession::where('user_id', $user->id)->update(['last_active_at' => now()->subMinutes(31)]);
        $this->expectException(\RuntimeException::class);
        $this->jwt->validateToken($tokens['access_token']);
    }

    public function test_refresh_token(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        $newTokens = $this->jwt->refreshToken($tokens['access_token'], Request::create('/'));
        $this->assertNotEquals($tokens['access_token'], $newTokens['access_token']);
    }

    public function test_refresh_revoked_throws(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        UserSession::where('user_id', $user->id)->update(['is_revoked' => true]);
        $this->expectException(\RuntimeException::class);
        $this->jwt->refreshToken($tokens['access_token'], Request::create('/'));
    }

    public function test_refresh_expired_session_throws(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        UserSession::where('user_id', $user->id)->update(['expires_at' => now()->subDay()]);
        $this->expectException(\RuntimeException::class);
        $this->jwt->refreshToken($tokens['access_token'], Request::create('/'));
    }

    public function test_refresh_disabled_user_throws(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        $user->update(['is_active' => false]);
        $this->expectException(\RuntimeException::class);
        $this->jwt->refreshToken($tokens['access_token'], Request::create('/'));
    }

    public function test_revoke_by_jti(): void
    {
        $user = $this->createUser('admin');
        $tokens = $this->jwt->issueToken($user, Request::create('/'));
        $payload = json_decode(base64_decode(explode('.', $tokens['access_token'])[1]), true);
        $this->jwt->revokeByJti($payload['jti']);
        $session = UserSession::where('jti', $payload['jti'])->first();
        $this->assertTrue($session->is_revoked);
    }

    public function test_revoke_all_for_user(): void
    {
        $user = $this->createUser('admin');
        $this->jwt->issueToken($user, Request::create('/'));
        $this->jwt->issueToken($user, Request::create('/'));
        $count = $this->jwt->revokeAllForUser($user->id);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_session_eviction_on_limit(): void
    {
        $user = $this->createUser('admin');
        $this->jwt->issueToken($user, Request::create('/'));
        $this->jwt->issueToken($user, Request::create('/'));
        $this->jwt->issueToken($user, Request::create('/')); // Should evict oldest
        $active = UserSession::where('user_id', $user->id)->where('is_revoked', false)->count();
        $this->assertLessThanOrEqual(2, $active);
    }
}
