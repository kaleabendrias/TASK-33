<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Provides an HTTP client pre-configured with the session JWT token
 * so Livewire components route all mutating actions through the API layer.
 */
trait UsesApiClient
{
    protected function api(): \Illuminate\Http\Client\PendingRequest
    {
        $token = session('jwt_token', '');
        $baseUrl = config('app.url', 'http://localhost:8080');

        return Http::baseUrl($baseUrl . '/api')
            ->withToken($token)
            ->acceptJson()
            ->timeout(15);
    }
}
