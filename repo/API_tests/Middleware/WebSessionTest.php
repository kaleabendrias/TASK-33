<?php

namespace ApiTests\Middleware;

use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use ApiTests\TestCase;

class WebSessionTest extends TestCase
{
    public function test_web_dashboard_redirects_without_session(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_web_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_web_dashboard_accessible_with_session(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        $this->withSession([
            'jwt_token' => $tokens['access_token'],
            'auth_role' => $user->role,
            'auth_user_name' => $user->full_name,
        ])->get('/dashboard')->assertOk();
    }

    public function test_web_session_with_expired_token_redirects(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        // Expire the session
        \App\Domain\Models\UserSession::where('user_id', $user->id)->update(['expires_at' => now()->subHour()]);

        $this->withSession(['jwt_token' => $tokens['access_token']])
            ->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_web_session_with_inactive_user_redirects(): void
    {
        $user = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        // Deactivate the user — middleware must reject the session.
        $user->update(['is_active' => false]);

        $this->withSession(['jwt_token' => $tokens['access_token']])
            ->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_profile_page_accessible(): void
    {
        $user = $this->createUser('staff');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));

        $this->withSession([
            'jwt_token' => $tokens['access_token'],
            'auth_role' => 'staff',
        ])->get('/profile')->assertOk();
    }
}
