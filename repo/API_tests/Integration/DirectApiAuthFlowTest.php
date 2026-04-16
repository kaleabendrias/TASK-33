<?php

namespace ApiTests\Integration;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use ApiTests\TestCase;

/**
 * Direct API counterparts for scenarios currently covered only by
 * web-session / Livewire-mediated tests in SettlementAccessIntegrationTest.
 *
 * These tests hit the API endpoints directly with Bearer tokens,
 * validating the same authorization semantics without going through
 * the Livewire component layer.
 */
class DirectApiAuthFlowTest extends TestCase
{
    private function issueBearer(User $user): array
    {
        $tokens = app(JwtService::class)->issueToken($user, Request::create('/'));
        return [
            'Authorization' => 'Bearer ' . $tokens['access_token'],
            'Accept'        => 'application/json',
        ];
    }

    private function staffWithProfile(string $role = 'staff'): User
    {
        $user = $this->createUser($role);
        StaffProfile::create([
            'user_id' => $user->id, 'employee_id' => 'EP-' . mt_rand(),
            'department' => 'Direct', 'title' => ucfirst($role),
        ]);
        return $user;
    }

    private function confirmedOrder(User $user): Order
    {
        return Order::create([
            'order_number' => 'DIR-' . mt_rand(),
            'user_id'      => $user->id,
            'status'       => 'confirmed',
            'subtotal'     => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);
    }

    // ── JWT authentication ─────────────────────────────────────────────

    /**
     * A freshly issued Bearer token authenticates an API call and returns
     * the correct user payload — validates JWT issuance → middleware → response.
     */
    public function test_valid_bearer_token_authenticates_api_request(): void
    {
        $user = $this->createUser('staff');
        $this->getJson('/api/auth/me', $this->issueBearer($user))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'staff');
    }

    /**
     * A missing Authorization header is rejected with 401 and a JSON error body.
     */
    public function test_missing_bearer_token_returns_401_with_message(): void
    {
        $this->getJson('/api/auth/me', ['Accept' => 'application/json'])
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    /**
     * A tampered / invalid token is rejected with 401.
     */
    public function test_invalid_bearer_token_returns_401(): void
    {
        $this->getJson('/api/orders', [
            'Authorization' => 'Bearer not.a.real.jwt',
            'Accept'        => 'application/json',
        ])->assertStatus(401)
          ->assertJsonStructure(['message']);
    }

    // ── Order authorization (direct API) ──────────────────────────────

    /**
     * Direct API counterpart of test_web_session_order_show_authorizes_owner.
     * Owner can view their own order via GET /api/orders/{id}.
     */
    public function test_order_api_returns_200_for_owner(): void
    {
        $owner = $this->createUser('user');
        $order = $this->confirmedOrder($owner);

        $this->getJson("/api/orders/{$order->id}", $this->issueBearer($owner))
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'confirmed');
    }

    /**
     * Direct API counterpart of test_web_session_order_show_blocks_non_owner.
     * Non-owner receives 403 with a machine-readable message body.
     */
    public function test_order_api_returns_403_for_non_owner(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $order = $this->confirmedOrder($owner);

        $this->getJson("/api/orders/{$order->id}", $this->issueBearer($other))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    /**
     * Admin bypasses row-level scoping and can view any order via the API.
     */
    public function test_admin_can_view_any_order_via_api(): void
    {
        $owner = $this->createUser('user');
        $admin = $this->createUser('admin');
        $order = $this->confirmedOrder($owner);

        $this->getJson("/api/orders/{$order->id}", $this->issueBearer($admin))
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    /**
     * Non-existent order returns 404 with a message body (not 403 or 500).
     */
    public function test_order_api_returns_404_for_nonexistent_id(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/orders/9999999', $this->issueBearer($user))
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    // ── Dashboard API (all roles) ─────────────────────────────────────

    /**
     * Direct API counterpart of test_web_session_login_hydrates_auth_guard_for_gate.
     * Validates that a Bearer-authenticated request to the dashboard stats
     * endpoint succeeds for each role — the same authorization the web
     * layer checks via Gate is enforced in the API middleware stack.
     */
    public function test_dashboard_stats_api_accessible_for_admin(): void
    {
        $admin = $this->createUser('admin');
        $this->getJson('/api/dashboard/stats', $this->issueBearer($admin))
            ->assertOk()
            ->assertJsonStructure(['data' => ['role', 'totalItems']]);
    }

    public function test_dashboard_stats_api_accessible_for_staff(): void
    {
        $staff = $this->staffWithProfile('staff');
        $this->getJson('/api/dashboard/stats', $this->issueBearer($staff))
            ->assertOk()
            ->assertJsonStructure(['data' => ['role']]);
    }

    public function test_dashboard_stats_api_accessible_for_group_leader(): void
    {
        $gl = $this->staffWithProfile('group-leader');
        $this->getJson('/api/dashboard/stats', $this->issueBearer($gl))
            ->assertOk()
            ->assertJsonStructure(['data' => ['role']]);
    }

    public function test_dashboard_stats_api_accessible_for_user(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/dashboard/stats', $this->issueBearer($user))
            ->assertOk()
            ->assertJsonStructure(['data' => ['role']]);
    }

    // ── Profile API (write/read cycle) ────────────────────────────────

    /**
     * Staff member can create/update their profile via PUT /api/profile
     * and retrieve it via GET /api/profile — the complete write→read cycle.
     */
    public function test_profile_write_read_cycle_via_api(): void
    {
        $staff = $this->createUser('staff');
        $headers = $this->issueBearer($staff);

        // Write profile
        $this->putJson('/api/profile', [
            'employee_id' => 'DIR-E99',
            'department'  => 'Direct Test',
            'title'       => 'Direct Engineer',
        ], $headers)->assertOk();

        // Read it back — same endpoint, same credentials
        $this->getJson('/api/profile', $headers)
            ->assertOk()
            ->assertJsonPath('data.employee_id', 'DIR-E99')
            ->assertJsonPath('data.department', 'Direct Test');
    }

    // ── Settlement authorization (direct API) ─────────────────────────

    /**
     * Settlement list is accessible to staff+ via the API.
     * Regular users receive 403 with a message body.
     */
    public function test_settlements_api_returns_403_for_plain_user(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/settlements', $this->issueBearer($user))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    /**
     * Settlement list is accessible to staff via the API with the
     * expected pagination envelope.
     */
    public function test_settlements_api_accessible_for_staff(): void
    {
        $staff = $this->staffWithProfile('staff');
        $this->getJson('/api/settlements', $this->issueBearer($staff))
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    // ── Response contract: error shape ───────────────────────────────

    /**
     * All 403 responses from the API include a machine-readable {message}
     * field — not just an HTTP status — so clients can display actionable text.
     */
    public function test_403_responses_include_message_field(): void
    {
        $user = $this->createUser('user');
        $headers = $this->issueBearer($user);

        // service-areas write is admin-only
        $this->postJson('/api/service-areas', ['name' => 'X'], $headers)
            ->assertStatus(403)
            ->assertJsonStructure(['message']);

        // admin users list
        $this->getJson('/api/admin/users', $headers)
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    /**
     * All 401 responses from the API include a machine-readable {message} field.
     */
    public function test_401_responses_include_message_field(): void
    {
        $bare = ['Accept' => 'application/json'];
        $this->getJson('/api/orders', $bare)
            ->assertStatus(401)
            ->assertJsonStructure(['message']);

        $this->getJson('/api/settlements', $bare)
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }
}
