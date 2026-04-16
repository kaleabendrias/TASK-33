<?php

namespace UnitTests;

use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends \Tests\TestCase
{
    use RefreshDatabase;

    protected function createUser(string $role = 'admin', array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'unit_' . $role . '_' . mt_rand(1000, 9999),
            'password' => 'TestPass@12345!',
            'full_name' => ucfirst($role) . ' User',
            'role' => $role,
            'is_active' => true,
        ], $overrides));
    }

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
