<?php

namespace UnitTests\Domain\Traits;

use App\Domain\Models\User;
use UnitTests\TestCase;

class EncryptionEdgeCasesTest extends TestCase
{
    public function test_decrypt_corrupted_field_returns_null(): void
    {
        $user = User::create(['username' => 'corrupt', 'password' => 'TestPass@12345!', 'full_name' => 'C', 'role' => 'user']);
        // Corrupt the encrypted field directly
        \DB::table('users')->where('id', $user->id)->update(['email_encrypted' => 'not_encrypted_data']);
        $user->refresh();
        $this->assertNull($user->decryptField('email_encrypted'));
    }

    public function test_find_by_encrypted_field_nonexistent_returns_null(): void
    {
        $this->assertNull(User::findByEncryptedField('email_encrypted', 'nonexistent@test.com'));
    }

    public function test_find_by_non_hash_field_returns_null(): void
    {
        // phone_encrypted has a hash field, but using a field without hash mapping
        $result = User::findByEncryptedField('password', 'anything');
        $this->assertNull($result);
    }

    public function test_encrypts_on_update(): void
    {
        $user = User::create(['username' => 'upd_enc', 'password' => 'TestPass@12345!', 'full_name' => 'U', 'role' => 'user', 'email_encrypted' => 'first@test.com']);
        $user->email_encrypted = 'updated@test.com';
        $user->save();
        $user->refresh();
        $this->assertEquals('updated@test.com', $user->decryptField('email_encrypted'));
        $raw = \DB::table('users')->where('id', $user->id)->value('email_encrypted');
        $this->assertNotEquals('updated@test.com', $raw);
    }
}
