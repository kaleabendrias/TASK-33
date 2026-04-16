<?php

namespace ApiTests\Auth;

use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use ApiTests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_success(): void
    {
        $this->createUser('admin', ['username' => 'api_admin']);
        $response = $this->postJson('/api/auth/login', ['username' => 'api_admin', 'password' => 'TestPass@12345!']);
        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_until']);
    }

    public function test_login_invalid_credentials(): void
    {
        $this->createUser('admin', ['username' => 'api_bad']);
        $this->postJson('/api/auth/login', ['username' => 'api_bad', 'password' => 'WrongPassword@1!'])
            ->assertStatus(422);
    }

    public function test_login_disabled_account(): void
    {
        $this->createUser('admin', ['username' => 'disabled', 'is_active' => false]);
        $this->postJson('/api/auth/login', ['username' => 'disabled', 'password' => 'TestPass@12345!'])
            ->assertStatus(422);
    }

    public function test_login_missing_fields(): void
    {
        $this->postJson('/api/auth/login', [])->assertStatus(422);
    }

    public function test_me_endpoint(): void
    {
        $user = $this->createUser('staff', ['username' => 'me_test']);
        $this->getJson('/api/auth/me', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.username', 'me_test')
            ->assertJsonPath('data.role', 'staff');
    }

    public function test_me_without_token(): void
    {
        $this->getJson('/api/auth/me', ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_logout(): void
    {
        $user = $this->createUser('admin', ['username' => 'logout_api']);
        $headers = $this->authHeaders($user);
        $this->postJson('/api/auth/logout', [], $headers)
            ->assertOk()
            ->assertJsonStructure(['message']);
        // Token should now be revoked
        $this->getJson('/api/auth/me', $headers)
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_refresh_token(): void
    {
        $user = $this->createUser('admin', ['username' => 'refresh_api']);
        $login = $this->postJson('/api/auth/login', ['username' => 'refresh_api', 'password' => 'TestPass@12345!']);
        $token = $login->json('access_token');

        $this->postJson('/api/auth/refresh', [], [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_concurrent_session_limit(): void
    {
        $user = $this->createUser('admin', ['username' => 'sess_limit']);
        // Login 3 times — max_sessions is 2; each login returns a token
        $r1 = $this->postJson('/api/auth/login', ['username' => 'sess_limit', 'password' => 'TestPass@12345!']);
        $r2 = $this->postJson('/api/auth/login', ['username' => 'sess_limit', 'password' => 'TestPass@12345!']);
        $r3 = $this->postJson('/api/auth/login', ['username' => 'sess_limit', 'password' => 'TestPass@12345!']);
        $r1->assertOk()->assertJsonStructure(['access_token']);
        $r2->assertOk()->assertJsonStructure(['access_token']);
        $r3->assertOk()->assertJsonStructure(['access_token']);

        // After the 3rd login the oldest session must have been auto-revoked
        $active = UserSession::where('user_id', $user->id)->where('is_revoked', false)->count();
        $this->assertLessThanOrEqual(2, $active);
    }

    public function test_expired_token_rejected(): void
    {
        $user = $this->createUser('admin', ['username' => 'expired_tok']);
        $headers = $this->authHeaders($user);
        // Mark session as expired
        UserSession::where('user_id', $user->id)->update(['expires_at' => now()->subHour()]);
        $this->getJson('/api/auth/me', $headers)->assertStatus(401);
    }

    public function test_inactive_session_rejected(): void
    {
        $user = $this->createUser('admin', ['username' => 'inactive_tok']);
        $headers = $this->authHeaders($user);
        UserSession::where('user_id', $user->id)->update(['last_active_at' => now()->subMinutes(31)]);
        $this->getJson('/api/auth/me', $headers)->assertStatus(401);
    }

    public function test_revoked_token_rejected(): void
    {
        $user = $this->createUser('admin', ['username' => 'revoked_tok']);
        $headers = $this->authHeaders($user);
        UserSession::where('user_id', $user->id)->update(['is_revoked' => true]);
        $this->getJson('/api/auth/me', $headers)->assertStatus(401);
    }
}
