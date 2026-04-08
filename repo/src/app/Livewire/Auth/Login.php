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
        //
        // JWTs use base64url (RFC 4648 §5): '-' / '_' instead of '+' / '/',
        // and padding is stripped. Plain base64_decode() silently corrupts
        // any payload containing those characters, which left auth_role
        // unset for ~25% of issued tokens. Use the RFC4648-compliant
        // decoder so role extraction is reliable.
        $parts = explode('.', $token);
        if (count($parts) >= 2) {
            $payload = json_decode($this->base64UrlDecode($parts[1]), true) ?? [];
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

    /**
     * RFC 4648 §5 base64url decoder. Translates URL-safe alphabet back to
     * standard base64 and re-pads with '=' so PHP's base64_decode (strict)
     * accepts the input. Returns an empty string if decoding fails.
     */
    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
