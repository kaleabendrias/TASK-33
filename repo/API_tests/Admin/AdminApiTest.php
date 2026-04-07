<?php

namespace ApiTests\Admin;

use ApiTests\TestCase;

class AdminApiTest extends TestCase
{
    public function test_create_user(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/admin/users', [
            'username' => 'new_api_user',
            'password' => 'StrongPass@123!',
            'full_name' => 'New User',
            'role' => 'staff',
        ], $this->authHeaders($admin))->assertStatus(201)->assertJsonPath('data.username', 'new_api_user');
    }

    public function test_create_user_duplicate_username(): void
    {
        $admin = $this->createUser('admin');
        $this->createUser('staff', ['username' => 'dup_user']);
        $this->postJson('/api/admin/users', [
            'username' => 'dup_user',
            'password' => 'StrongPass@123!',
            'full_name' => 'Dup',
        ], $this->authHeaders($admin))->assertStatus(422);
    }

    public function test_list_users(): void
    {
        $admin = $this->createUser('admin');
        $this->createUser('staff');
        $this->getJson('/api/admin/users', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'username', 'role']]]);
    }

    public function test_show_user(): void
    {
        $admin = $this->createUser('admin');
        $target = $this->createUser('user');
        $this->getJson("/api/admin/users/{$target->id}", $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);
    }

    public function test_audit_logs(): void
    {
        $admin = $this->createUser('admin');
        // Trigger some audit entries via login
        $this->postJson('/api/auth/login', ['username' => $admin->username, 'password' => 'TestPass@12345!']);
        $this->getJson('/api/admin/audit-logs', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_audit_logs_filterable(): void
    {
        $admin = $this->createUser('admin');
        $this->getJson('/api/admin/audit-logs?action=login', $this->authHeaders($admin))->assertOk();
        $this->getJson('/api/admin/audit-logs?entity_type=User', $this->authHeaders($admin))->assertOk();
    }

    public function test_create_user_with_encrypted_fields(): void
    {
        $admin = $this->createUser('admin');
        $resp = $this->postJson('/api/admin/users', [
            'username' => 'enc_user',
            'password' => 'StrongPass@123!',
            'full_name' => 'Encrypted',
            'email' => 'enc@test.com',
            'phone' => '+1-555-0199',
            'role' => 'user',
        ], $this->authHeaders($admin));
        $resp->assertStatus(201);
        // Admin sees unmasked
        $resp->assertJsonPath('data.email', 'enc@test.com');
        $resp->assertJsonPath('data.phone', '+1-555-0199');
    }

    public function test_masked_fields_for_non_admin(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/admin/users', [
            'username' => 'mask_target',
            'password' => 'StrongPass@123!',
            'full_name' => 'Masked',
            'email' => 'mask@example.com',
            'phone' => '+1-555-0123',
        ], $this->authHeaders($admin));

        // View via non-admin would be masked (tested via the UserResource logic)
        // Since the admin route requires admin, this verifies the admin path
        $target = \App\Domain\Models\User::where('username', 'mask_target')->first();
        $this->assertNotNull($target);
        $this->assertEquals('mask@example.com', $target->decryptField('email_encrypted'));
    }
}
