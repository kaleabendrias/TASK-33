<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\User;
use UnitTests\TestCase;

class UserModelTest extends TestCase
{
    public function test_password_is_hashed_on_create(): void
    {
        $user = User::create(['username' => 'hashtest', 'password' => 'RawPassword@123', 'full_name' => 'Test', 'role' => 'user']);
        $this->assertNotEquals('RawPassword@123', $user->password);
        $this->assertTrue($user->verifyPassword('RawPassword@123'));
    }

    public function test_verify_password_fails_for_wrong_input(): void
    {
        $user = User::create(['username' => 'pw_wrong', 'password' => 'CorrectPass@123', 'full_name' => 'Test', 'role' => 'user']);
        $this->assertFalse($user->verifyPassword('WrongPassword@1'));
    }

    public function test_is_admin(): void
    {
        $admin = new User(); $admin->role = 'admin';
        $user = new User(); $user->role = 'user';
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());
    }

    public function test_is_at_least_hierarchy(): void
    {
        $admin = new User(); $admin->role = 'admin';
        $gl = new User(); $gl->role = 'group-leader';
        $staff = new User(); $staff->role = 'staff';
        $user = new User(); $user->role = 'user';

        $this->assertTrue($admin->isAtLeast('admin'));
        $this->assertTrue($admin->isAtLeast('user'));
        $this->assertTrue($gl->isAtLeast('staff'));
        $this->assertFalse($staff->isAtLeast('group-leader'));
        $this->assertFalse($user->isAtLeast('staff'));
    }

    public function test_encrypted_fields_are_stored_encrypted(): void
    {
        $user = User::create([
            'username' => 'enc_test', 'password' => 'TestPass@12345!',
            'full_name' => 'Enc Test', 'role' => 'user',
            'email_encrypted' => 'test@example.com', 'phone_encrypted' => '+1-555-0100',
        ]);

        // Raw DB value should be encrypted (not plain)
        $raw = \DB::table('users')->where('id', $user->id)->first();
        $this->assertNotEquals('test@example.com', $raw->email_encrypted);
        $this->assertNotEmpty($raw->email_hash);

        // Decrypt should return original
        $this->assertEquals('test@example.com', $user->decryptField('email_encrypted'));
        $this->assertEquals('+1-555-0100', $user->decryptField('phone_encrypted'));
    }

    public function test_sha256_hash_index_populated(): void
    {
        $user = User::create([
            'username' => 'hash_idx', 'password' => 'TestPass@12345!',
            'full_name' => 'Hash Test', 'role' => 'user',
            'email_encrypted' => 'Hash@Example.COM',
        ]);
        $expected = hash('sha256', 'hash@example.com'); // lowered + trimmed
        $this->assertEquals($expected, $user->email_hash);
    }

    public function test_find_by_encrypted_field(): void
    {
        User::create([
            'username' => 'lookup_test', 'password' => 'TestPass@12345!',
            'full_name' => 'Lookup', 'role' => 'user',
            'email_encrypted' => 'lookup@test.com',
        ]);
        $found = User::findByEncryptedField('email_encrypted', 'lookup@test.com');
        $this->assertNotNull($found);
        $this->assertEquals('lookup_test', $found->username);
    }

    public function test_decrypt_field_returns_null_for_null(): void
    {
        $user = User::create(['username' => 'nulldec', 'password' => 'TestPass@12345!', 'full_name' => 'N', 'role' => 'user']);
        $this->assertNull($user->decryptField('email_encrypted'));
    }

    public function test_password_hidden_from_serialization(): void
    {
        $user = User::create(['username' => 'hidden', 'password' => 'TestPass@12345!', 'full_name' => 'H', 'role' => 'user']);
        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('email_encrypted', $array);
    }
}
