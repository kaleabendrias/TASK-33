<?php

namespace UnitTests\Infrastructure\Middleware;

use App\Api\Middleware\JwtAuthenticate;
use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use UnitTests\TestCase;

class JwtAuthenticateTest extends TestCase
{
    private JwtAuthenticate $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = app(JwtAuthenticate::class);
    }

    private function next(): \Closure
    {
        return fn($req) => response()->json(['ok' => true], 200);
    }

    public function test_rejects_missing_authorization_header(): void
    {
        $request = Request::create('/api/test');
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_rejects_non_bearer_authorization_scheme(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_malformed_jwt(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer garbage.token.here');
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_allows_pre_stamped_active_user(): void
    {
        $user = $this->createUser('user');
        $request = Request::create('/api/test');
        $request->attributes->set('auth_user', $user);
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_pre_stamped_inactive_user(): void
    {
        $user = $this->createUser('user', ['is_active' => false]);
        $request = Request::create('/api/test');
        $request->attributes->set('auth_user', $user);
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_allows_valid_jwt_and_stamps_auth_user(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer ' . $tokens['access_token']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertEquals(200, $response->getStatusCode());
        $stamped = $request->attributes->get('auth_user');
        $this->assertInstanceOf(User::class, $stamped);
        $this->assertEquals($user->id, $stamped->id);
    }

    public function test_rejects_revoked_jwt(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));
        UserSession::where('user_id', $user->id)->update(['is_revoked' => true]);

        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer ' . $tokens['access_token']);
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_expired_session_jwt(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));
        UserSession::where('user_id', $user->id)->update(['expires_at' => now()->subHour()]);

        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer ' . $tokens['access_token']);
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_disabled_user_jwt(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));
        $user->update(['is_active' => false]);

        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer ' . $tokens['access_token']);
        $response = $this->middleware->handle($request, $this->next());
        $this->assertEquals(401, $response->getStatusCode());
    }
}
