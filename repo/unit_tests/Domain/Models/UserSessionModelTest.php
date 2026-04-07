<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\User;
use App\Domain\Models\UserSession;
use UnitTests\TestCase;

class UserSessionModelTest extends TestCase
{
    private function makeSession(array $overrides = []): UserSession
    {
        $user = User::create(['username' => 'sess_' . mt_rand(1000, 9999), 'password' => 'TestPass@12345!', 'full_name' => 'S', 'role' => 'user']);
        return UserSession::create(array_merge([
            'user_id' => $user->id, 'jti' => bin2hex(random_bytes(32)),
            'issued_at' => now(), 'expires_at' => now()->addDays(7), 'last_active_at' => now(),
        ], $overrides));
    }

    public function test_is_valid_active_session(): void
    {
        $session = $this->makeSession();
        $this->assertTrue($session->isValid());
    }

    public function test_is_expired(): void
    {
        $session = $this->makeSession(['expires_at' => now()->subHour()]);
        $this->assertTrue($session->isExpired());
        $this->assertFalse($session->isValid());
    }

    public function test_is_inactive(): void
    {
        $session = $this->makeSession(['last_active_at' => now()->subMinutes(31)]);
        $this->assertTrue($session->isInactive());
        $this->assertFalse($session->isValid());
    }

    public function test_revoked_is_invalid(): void
    {
        $session = $this->makeSession();
        $session->revoke('test');
        $this->assertTrue($session->is_revoked);
        $this->assertFalse($session->isValid());
        $this->assertEquals('test', $session->revoked_by);
        $this->assertNotNull($session->revoked_at);
    }

    public function test_touch_activity(): void
    {
        $session = $this->makeSession(['last_active_at' => now()->subMinutes(20)]);
        $oldTime = $session->last_active_at->copy();
        $session->touchActivity();
        $this->assertTrue($session->last_active_at->isAfter($oldTime));
    }
}
