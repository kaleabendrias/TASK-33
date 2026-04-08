<?php

namespace App\Livewire\Concerns;

use App\Domain\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request as IlluminateRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Internal-dispatch API client for Livewire components.
 *
 * Why not real HTTP? Two reasons:
 *
 *   1. PHPUnit runs the entire suite in-process — there is no nginx
 *      and no listening port, so a real `Http::baseUrl(...)->get()`
 *      from a Livewire component dies with cURL "connection refused".
 *      Even if a server were running, it would be talking to a
 *      different DB connection (the test runner uses a separate
 *      schema) so authentication state would not be visible.
 *
 *   2. In production, the Livewire component is already inside a
 *      Laravel HTTP request. A sub-request through the kernel reuses
 *      the same DB connection, the same container bindings, and the
 *      same authenticated user without paying the network round-trip
 *      tax — and authorization parity is preserved because we route
 *      through the SAME api.php routes the REST clients use.
 *
 * The api() trait still gives Livewire components the same surface
 * (`->get('/path')`, `->post('/path', $body)`, `->successful()`,
 * `->json()`) but the dispatch is in-process. The auth user is
 * threaded via the `auth_user` request attribute that the existing
 * jwt.auth middleware also sets, so downstream code is identical.
 */
trait UsesApiClient
{
    protected function api(): InternalApiClient
    {
        return new InternalApiClient($this->resolveAuthUser());
    }

    /**
     * Find the user this Livewire request is acting on behalf of.
     * In production this comes from the WebSessionAuth middleware
     * which sets `auth_user` on the current request. In tests it
     * comes from `actingAs($user)` which sets the auth guard.
     */
    private function resolveAuthUser(): ?User
    {
        $current = request()->attributes->get('auth_user');
        if ($current instanceof User) return $current;

        $authed = auth()->user();
        return $authed instanceof User ? $authed : null;
    }

    /**
     * Effective permission slugs for the authenticated user, fetched
     * through GET /auth/me. Cached on the component instance so a
     * single render only issues one sub-request. The blade can then
     * conditionally render buttons via:
     *
     *   @if(in_array('settlements.finalize', $effectivePermissions))
     *
     * which mirrors EXACTLY the slug enforced by the permission:*
     * middleware on the corresponding API mutation route.
     */
    protected ?array $cachedEffectivePermissions = null;

    protected function effectivePermissions(): array
    {
        if ($this->cachedEffectivePermissions !== null) {
            return $this->cachedEffectivePermissions;
        }
        $resp = $this->api()->get('/auth/me');
        if (!$resp->successful()) {
            return $this->cachedEffectivePermissions = [];
        }
        return $this->cachedEffectivePermissions = (array) ($resp->json('data.effective_permissions') ?? []);
    }
}

/**
 * Lightweight in-process API client. Mimics the parts of
 * Illuminate\Http\Client\PendingRequest the Livewire layer uses.
 */
class InternalApiClient
{
    public function __construct(private readonly ?User $authUser) {}

    public function get(string $path, array $query = []): InternalApiResponse
    {
        return $this->dispatch('GET', $path, $query);
    }

    public function post(string $path, array $body = []): InternalApiResponse
    {
        return $this->dispatch('POST', $path, [], $body);
    }

    public function put(string $path, array $body = []): InternalApiResponse
    {
        return $this->dispatch('PUT', $path, [], $body);
    }

    private function dispatch(string $method, string $path, array $query = [], array $body = []): InternalApiResponse
    {
        $url = '/api' . $path;
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $server = [
            'HTTP_ACCEPT'  => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        $content = !empty($body) ? json_encode($body) : null;

        $request = IlluminateRequest::create($url, $method, [], [], [], $server, $content);

        // Bypass jwt.auth by pre-stamping the auth_user attribute and
        // setting the auth guard. The jwt.auth middleware short-circuits
        // when an auth_user is already present. The kernel still runs
        // every other middleware — role:*, permission:*, profile.complete
        // — so authorization is enforced identically to a REST request.
        if ($this->authUser !== null) {
            $request->attributes->set('auth_user', $this->authUser);
            app('auth')->setUser($this->authUser);
        }

        // Livewire's testing harness binds `middleware.disable=true`
        // so its OWN component renders skip middleware. That flag is
        // a singleton on the container and leaks into ANY downstream
        // kernel->handle() call we make from inside the component.
        // Locally invert it for the duration of our sub-request so
        // jwt.auth, role:*, permission:* and friends actually run —
        // then restore the previous binding so we don't disturb the
        // outer Livewire test that depends on it.
        $previousDisable = app()->bound('middleware.disable')
            ? app()->make('middleware.disable')
            : null;
        app()->instance('middleware.disable', false);

        try {
            /** @var HttpKernel $kernel */
            $kernel = app(HttpKernel::class);
            $response = $kernel->handle($request);
        } catch (\Throwable $e) {
            // Convert exceptions into the response codes the same
            // request would yield in production. Laravel's exception
            // handler does this for top-level requests but not always
            // for in-process sub-requests, so we map the common
            // domain exceptions ourselves and fall back to the
            // framework handler for everything else.
            $response = $this->renderExceptionResponse($e, $request);
        } finally {
            if ($previousDisable === null) {
                app()->forgetInstance('middleware.disable');
            } else {
                app()->instance('middleware.disable', $previousDisable);
            }
        }

        return new InternalApiResponse($response);
    }

    private function renderExceptionResponse(\Throwable $e, IlluminateRequest $request): SymfonyResponse
    {
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
            || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
        // Re-throw anything we don't recognise so the surrounding test
        // (or production exception handler) sees the real error rather
        // than a swallowed 500.
        throw $e;
    }
}

/**
 * Lightweight response wrapper that mimics the parts of
 * Illuminate\Http\Client\Response the Livewire layer uses.
 */
class InternalApiResponse
{
    public function __construct(private readonly SymfonyResponse $response) {}

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function successful(): bool
    {
        return $this->status() < 300;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = json_decode($this->response->getContent(), true);
        if (!is_array($decoded)) return $key === null ? [] : $default;
        if ($key === null) return $decoded;
        return data_get($decoded, $key, $default);
    }

    /**
     * Raw response body (used by exporters that stream CSV/PDF).
     */
    public function body(): string
    {
        return (string) $this->response->getContent();
    }
}
