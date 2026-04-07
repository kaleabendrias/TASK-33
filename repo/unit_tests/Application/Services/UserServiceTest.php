<?php

namespace UnitTests\Application\Services;

use App\Application\Services\UserService;
use App\Domain\Models\User;
use Illuminate\Validation\ValidationException;
use UnitTests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    public function test_create_user(): void
    {
        $user = $this->service->create([
            'username' => 'svc_create', 'password' => 'StrongPass@123!',
            'full_name' => 'Test User', 'role' => 'staff',
        ]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('svc_create', $user->username);
    }

    public function test_create_user_weak_password_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create([
            'username' => 'svc_weak', 'password' => 'weak',
            'full_name' => 'Test', 'role' => 'user',
        ]);
    }

    public function test_update_user(): void
    {
        $user = $this->service->create([
            'username' => 'svc_update', 'password' => 'StrongPass@123!',
            'full_name' => 'Old Name', 'role' => 'user',
        ]);
        $updated = $this->service->update($user->id, ['full_name' => 'New Name']);
        $this->assertEquals('New Name', $updated->full_name);
    }

    public function test_update_password_validates(): void
    {
        $user = $this->service->create([
            'username' => 'svc_pw_upd', 'password' => 'StrongPass@123!',
            'full_name' => 'PW', 'role' => 'user',
        ]);
        $this->expectException(ValidationException::class);
        $this->service->update($user->id, ['password' => 'short']);
    }

    public function test_list_users(): void
    {
        User::create(['username' => 'list_a', 'password' => 'TestPass@12345!', 'full_name' => 'A', 'role' => 'user']);
        User::create(['username' => 'list_b', 'password' => 'TestPass@12345!', 'full_name' => 'B', 'role' => 'admin']);
        $list = $this->service->list();
        $this->assertGreaterThanOrEqual(2, $list->count());
    }

    public function test_get_user(): void
    {
        $user = User::create(['username' => 'get_me', 'password' => 'TestPass@12345!', 'full_name' => 'G', 'role' => 'user']);
        $found = $this->service->get($user->id);
        $this->assertEquals($user->id, $found->id);
    }
}
