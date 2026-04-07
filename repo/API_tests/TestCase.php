<?php

namespace ApiTests;

use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends \Tests\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush permission cache so test-seeded permissions are visible
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function authHeaders(User $user): array
    {
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, request());
        return [
            'Authorization' => 'Bearer ' . $tokens['access_token'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function createUser(string $role = 'admin', array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'test_' . $role . '_' . mt_rand(1000, 9999),
            'password' => 'TestPass@12345!',
            'full_name' => ucfirst($role) . ' User',
            'role' => $role,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Create a staff+ user WITH a complete profile so profile.complete middleware passes.
     */
    protected function createStaffWithProfile(string $role = 'staff', array $overrides = []): User
    {
        $user = $this->createUser($role, $overrides);
        StaffProfile::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-' . mt_rand(100, 999),
            'department' => 'Testing',
            'title' => 'Test ' . ucfirst($role),
        ]);
        return $user;
    }
}
