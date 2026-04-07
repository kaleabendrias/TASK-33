<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sign In — ServicePlatform')]
class Login extends Component
{
    public string $username = '';
    public string $password = '';
    public string $error = '';

    /**
     * API-decoupled login: posts credentials to /api/auth/login and stores
     * the returned access token in the web session. The component never
     * touches AuthService or the User model directly.
     */
    public function login(): void
    {
        $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $baseUrl = config('app.url', 'http://localhost:8080');
        $resp = Http::baseUrl($baseUrl . '/api')
            ->acceptJson()
            ->timeout(15)
            ->post('/auth/login', [
                'username' => $this->username,
                'password' => $this->password,
            ]);

        if ($resp->failed() || empty($resp->json('access_token'))) {
            $this->error = 'Invalid username or password.';
            return;
        }

        $token = $resp->json('access_token');
        Session::put('jwt_token', $token);

        // Decode the JWT payload (the API returns role/sub server-side, but we
        // also peek at the unverified body for session metadata — the token
        // itself is the source of truth, this is just for display).
        $parts = explode('.', $token);
        if (count($parts) >= 2) {
            $payload = json_decode(base64_decode($parts[1]), true) ?? [];
            Session::put('auth_user_id', $payload['sub'] ?? null);
            Session::put('auth_role', $payload['role'] ?? 'user');
        }
        Session::put('auth_user_name', $resp->json('user.full_name', $this->username));

        $this->redirect('/dashboard');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
