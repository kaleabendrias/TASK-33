<?php

namespace FrontendTests;

use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base class for frontend (Livewire component) unit tests.
 *
 * These tests verify the VIEW LAYER: component property defaults,
 * Livewire validation rules, pure state transitions, and rendering.
 * They never assert on raw HTTP response bodies, never use Http::fake(),
 * and never call API endpoints directly.
 *
 * Because Livewire components use InternalApiClient (kernel dispatch,
 * not the Http facade), Http::fake() would be dead code here anyway.
 * The test suite is intentionally free of fakes so every assertion
 * reflects real application behaviour.
 */
abstract class TestCase extends \Tests\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function createUser(string $role = 'user', array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'fe_' . $role . '_' . mt_rand(1000, 9999),
            'password' => 'TestPass@12345!',
            'full_name' => ucfirst($role) . ' User',
            'role'      => $role,
            'is_active' => true,
        ], $overrides));
    }

    protected function createStaffWithProfile(string $role = 'staff'): User
    {
        $user = $this->createUser($role);
        StaffProfile::create([
            'user_id'     => $user->id,
            'employee_id' => 'EMP-' . mt_rand(100, 999),
            'department'  => 'Testing',
            'title'       => 'Test ' . ucfirst($role),
        ]);
        return $user;
    }

    protected function actAs(User $user): void
    {
        $this->actingAs($user);
        request()->attributes->set('auth_user', $user);
    }
}
