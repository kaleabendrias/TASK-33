<?php

namespace UnitTests\Application\Services;

use App\Application\Services\AuthService;
use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use UnitTests\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuthService::class);
    }

    private function makeUser(array $o = []): User
    {
        static $n = 0;
        return User::create(array_merge([
            'username' => 'auth_user_' . ++$n, 'password' => 'ValidPass@12345!',
            'full_name' => 'Auth Test', 'role' => 'admin', 'is_active' => true,
        ], $o));
    }

    public function test_login_success(): void
    {
        $user = $this->makeUser(['username' => 'login_ok']);
        $tokens = $this->service->login('login_ok', 'ValidPass@12345!', Request::create('/'));
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        $this->assertEquals('Bearer', $tokens['token_type']);
    }

    public function test_login_wrong_password_throws(): void
    {
        $this->makeUser(['username' => 'login_fail']);
        $this->expectException(ValidationException::class);
        $this->service->login('login_fail', 'WrongPassword@1!', Request::create('/'));
    }

    public function test_login_nonexistent_user_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->login('nonexistent_user', 'Whatever@1234!', Request::create('/'));
    }

    public function test_login_disabled_account_throws(): void
    {
        $this->makeUser(['username' => 'disabled_acct', 'is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->service->login('disabled_acct', 'ValidPass@12345!', Request::create('/'));
    }

    public function test_login_creates_session(): void
    {
        $user = $this->makeUser(['username' => 'sess_test']);
        $this->service->login('sess_test', 'ValidPass@12345!', Request::create('/'));
        $this->assertCount(1, UserSession::where('user_id', $user->id)->get());
    }

    public function test_max_sessions_enforced(): void
    {
        $user = $this->makeUser(['username' => 'max_sess']);
        $req = Request::create('/');

        $this->service->login('max_sess', 'ValidPass@12345!', $req);
        $this->service->login('max_sess', 'ValidPass@12345!', $req);
        $this->service->login('max_sess', 'ValidPass@12345!', $req); // Should evict oldest

        $active = UserSession::where('user_id', $user->id)->where('is_revoked', false)->count();
        $this->assertLessThanOrEqual(2, $active);
    }

    public function test_logout(): void
    {
        $user = $this->makeUser(['username' => 'logout_test']);
        $tokens = $this->service->login('logout_test', 'ValidPass@12345!', Request::create('/'));

        $payload = json_decode(base64_decode(explode('.', $tokens['access_token'])[1]), true);
        $session = UserSession::where('jti', $payload['jti'])->first();

        $request = Request::create('/');
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_session', $session);

        $this->service->logout($request);
        $this->assertTrue($session->refresh()->is_revoked);
    }

    public function test_admin_revoke_tokens(): void
    {
        $user = $this->makeUser(['username' => 'revoke_test']);
        $this->service->login('revoke_test', 'ValidPass@12345!', Request::create('/'));
        $this->service->login('revoke_test', 'ValidPass@12345!', Request::create('/'));

        $count = $this->service->adminRevokeTokens($user->id);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_admin_reset_password(): void
    {
        $user = $this->makeUser(['username' => 'reset_pw']);
        $this->service->login('reset_pw', 'ValidPass@12345!', Request::create('/'));
        $this->service->adminResetPassword($user->id, 'NewStrongPass@99!');

        $user->refresh();
        $this->assertTrue($user->verifyPassword('NewStrongPass@99!'));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_admin_reset_password_weak_throws(): void
    {
        $user = $this->makeUser(['username' => 'reset_weak']);
        $this->expectException(ValidationException::class);
        $this->service->adminResetPassword($user->id, 'short');
    }

    public function test_refresh_token(): void
    {
        $user = $this->makeUser(['username' => 'refresh_test']);
        $tokens = $this->service->login('refresh_test', 'ValidPass@12345!', Request::create('/'));
        $newTokens = $this->service->refresh($tokens['access_token'], Request::create('/'));
        $this->assertArrayHasKey('access_token', $newTokens);
        $this->assertNotEquals($tokens['access_token'], $newTokens['access_token']);
    }
}
