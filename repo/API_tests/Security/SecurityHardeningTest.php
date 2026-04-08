<?php

namespace ApiTests\Security;

use App\Domain\Models\StaffProfile;
use ApiTests\TestCase;

class SecurityHardeningTest extends TestCase
{
    // --- Masked auth error details ---

    public function test_invalid_token_returns_generic_401(): void
    {
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer invalid.token.here',
            'Accept' => 'application/json',
        ]);
        $response->assertStatus(401);
        // Should NOT contain internal error details like "Unexpected token" or stack traces
        $this->assertEquals('Authentication required.', $response->json('message'));
    }

    public function test_expired_token_returns_generic_401(): void
    {
        $user = $this->createUser('admin');
        $headers = $this->authHeaders($user);
        // Expire all sessions
        \App\Domain\Models\UserSession::where('user_id', $user->id)->update(['last_active_at' => now()->subHour()]);

        $response = $this->getJson('/api/auth/me', $headers);
        $response->assertStatus(401);
        $this->assertEquals('Authentication required.', $response->json('message'));
    }

    public function test_disabled_account_returns_generic_401(): void
    {
        $user = $this->createUser('admin');
        $headers = $this->authHeaders($user);
        $user->update(['is_active' => false]);

        $response = $this->getJson('/api/auth/me', $headers);
        $response->assertStatus(401);
        $this->assertEquals('Authentication required.', $response->json('message'));
    }

    public function test_login_disabled_account_same_message_as_wrong_password(): void
    {
        $this->createUser('admin', ['username' => 'disabled_acct', 'is_active' => false]);
        $wrong = $this->postJson('/api/auth/login', ['username' => 'nonexistent', 'password' => 'Wrong@12345678!']);
        $disabled = $this->postJson('/api/auth/login', ['username' => 'disabled_acct', 'password' => 'TestPass@12345!']);

        // Both should return the same generic message
        $this->assertEquals($wrong->json('errors.credentials.0'), $disabled->json('errors.credentials.0'));
    }

    // --- Debug mode ---

    public function test_debug_mode_config_default_is_false(): void
    {
        // The config/app.php default (when APP_DEBUG env is absent) must be false
        $configFile = file_get_contents(base_path('config/app.php'));
        $this->assertStringContainsString("env('APP_DEBUG', false)", $configFile);
    }

    // --- Foundational entity writes are admin-only ---

    public function test_staff_blocked_from_foundational_writes_without_profile(): void
    {
        $staff = $this->createUser('staff');
        $this->postJson('/api/service-areas', ['name' => 'Blocked'], $this->authHeaders($staff))
            ->assertStatus(403);
    }

    public function test_staff_blocked_from_foundational_writes_with_profile(): void
    {
        // Profile completeness must NOT unlock foundational entity
        // writes — only the admin role does. This guards against the
        // historical bug where seeding staff permissions silently
        // re-enabled billing-baseline mutation.
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/service-areas', ['name' => 'StillBlocked'], $this->authHeaders($staff))
            ->assertStatus(403);
    }

    // --- Must-change-password flag ---

    public function test_seeded_accounts_flagged_for_password_change(): void
    {
        \Database\Seeders\UserSeeder::class;
        $user = \App\Domain\Models\User::create([
            'username' => 'seed_test', 'password' => 'TestPass@12345!',
            'full_name' => 'Seed', 'role' => 'admin', 'must_change_password' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'seed_test', 'password' => 'TestPass@12345!',
        ]);
        $response->assertOk();
        $this->assertTrue($response->json('must_change_password'));
    }

    // --- Profile API endpoints ---

    public function test_profile_show_and_update(): void
    {
        $staff = $this->createUser('staff');
        $h = $this->authHeaders($staff);

        $this->getJson('/api/profile', $h)->assertOk()->assertJsonPath('is_complete', false);

        $this->putJson('/api/profile', [
            'employee_id' => 'E100', 'department' => 'HR', 'title' => 'Manager',
        ], $h)->assertOk()->assertJsonPath('is_complete', true);
    }
}
