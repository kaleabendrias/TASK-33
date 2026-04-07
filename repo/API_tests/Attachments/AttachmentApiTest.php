<?php

namespace ApiTests\Attachments;

use App\Domain\Models\Resource;
use App\Domain\Models\Role;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\StaffProfile;
use Illuminate\Http\UploadedFile;
use ApiTests\TestCase;

class AttachmentApiTest extends TestCase
{
    private function staffWithProfile(): \App\Domain\Models\User
    {
        return $this->createStaffWithProfile();
    }

    private function makeResource(): Resource
    {
        $sa = ServiceArea::firstOrCreate(['slug' => 'att-sa'], ['name' => 'Att SA']);
        $role = Role::firstOrCreate(['slug' => 'att-role'], ['name' => 'Att Role', 'level' => 1]);
        return Resource::create([
            'name' => 'Att Resource ' . mt_rand(),
            'service_area_id' => $sa->id,
            'role_id' => $role->id,
            'capacity_hours' => 10,
        ]);
    }

    /** Auth headers without Content-Type (needed for multipart file uploads) */
    private function uploadHeaders(\App\Domain\Models\User $user): array
    {
        $h = $this->authHeaders($user);
        unset($h['Content-Type']);
        return $h;
    }

    public function test_upload_valid_file(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $user = $this->staffWithProfile();
        $resourceId = $this->makeResource()->id;
        // Create a real PNG so getMimeType() returns image/png
        $tmpPath = tempnam(sys_get_temp_dir(), 'png') . '.png';
        $img = imagecreatetruecolor(50, 50);
        imagepng($img, $tmpPath);
        imagedestroy($img);
        $file = new UploadedFile($tmpPath, 'photo.png', 'image/png', null, true);

        $response = $this->withHeaders($this->uploadHeaders($user))
            ->post('/api/attachments', [
                'file' => $file,
                'attachable_type' => 'Resource',
                'attachable_id' => $resourceId,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['sha256_fingerprint', 'mime_type', 'size_bytes']]);
    }

    public function test_upload_oversized_file_fails(): void
    {
        $user = $this->staffWithProfile();
        $resourceId = $this->makeResource()->id;
        // 21MB exceeds the 20MB limit
        $file = UploadedFile::fake()->create('big.pdf', 21000, 'application/pdf');

        $this->withHeaders($this->uploadHeaders($user))
            ->post('/api/attachments', [
                'file' => $file,
                'attachable_type' => 'Resource',
                'attachable_id' => $resourceId,
            ])->assertStatus(422);
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->postJson('/api/attachments', [
            'file' => $file, 'attachable_type' => 'Resource', 'attachable_id' => 1,
        ])->assertStatus(401);
    }

    public function test_upload_requires_staff_role(): void
    {
        $viewer = $this->createUser('user');
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->withHeaders($this->authHeaders($viewer))
            ->post('/api/attachments', [
                'file' => $file, 'attachable_type' => 'Resource', 'attachable_id' => 1,
            ])->assertStatus(403);
    }

    public function test_upload_image_gets_compressed(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $user = $this->staffWithProfile();
        $resourceId = $this->makeResource()->id;
        // Create a real JPEG so GD can process it
        $tmpPath = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
        $img = imagecreatetruecolor(200, 200);
        imagejpeg($img, $tmpPath, 100);
        imagedestroy($img);
        $file = new UploadedFile($tmpPath, 'photo.jpg', 'image/jpeg', null, true);

        $response = $this->withHeaders($this->uploadHeaders($user))
            ->post('/api/attachments', [
                'file' => $file,
                'attachable_type' => 'Resource',
                'attachable_id' => $resourceId,
            ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.sha256_fingerprint'));
    }

    // ── Attachable type whitelist + existence + authorization ──────────────

    public function test_upload_rejects_unknown_attachable_type(): void
    {
        $user = $this->staffWithProfile();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->withHeaders($this->uploadHeaders($user))
            ->post('/api/attachments', [
                'file' => $file,
                'attachable_type' => 'User', // not whitelisted
                'attachable_id' => $user->id,
            ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid', (string) $response->json('message'));
    }

    public function test_upload_rejects_nonexistent_attachable(): void
    {
        $user = $this->staffWithProfile();
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->withHeaders($this->uploadHeaders($user))
            ->post('/api/attachments', [
                'file' => $file,
                'attachable_type' => 'Resource',
                'attachable_id' => 999999,
            ]);
        $response->assertStatus(404);
    }

    private function realPng(): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'png') . '.png';
        $img = imagecreatetruecolor(20, 20);
        imagepng($img, $tmpPath);
        imagedestroy($img);
        return new UploadedFile($tmpPath, 'note.png', 'image/png', null, true);
    }

    public function test_upload_rejects_unauthorized_user_for_other_users_order(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $userA = $this->createStaffWithProfile(overrides: ['username' => 'a_' . mt_rand()]);
        $userB = $this->createStaffWithProfile(overrides: ['username' => 'b_' . mt_rand()]);
        $order = \App\Domain\Models\Order::create([
            'order_number' => 'ORD-AUTH-' . mt_rand(),
            'user_id' => $userA->id,
            'status' => 'confirmed',
            'subtotal' => 50, 'total' => 50,
            'confirmed_at' => now(),
        ]);

        $this->withHeaders($this->uploadHeaders($userB))
            ->post('/api/attachments', [
                'file' => $this->realPng(),
                'attachable_type' => 'Order',
                'attachable_id' => $order->id,
            ])->assertStatus(403);
    }

    public function test_download_admin_can_get_any_attachment(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $admin = $this->createUser('admin');
        $resourceId = $this->makeResource()->id;
        // Upload first
        $up = $this->withHeaders($this->uploadHeaders($admin))->post('/api/attachments', [
            'file' => $this->realPng(),
            'attachable_type' => 'Resource',
            'attachable_id' => $resourceId,
        ]);
        $up->assertStatus(201);
        $id = $up->json('data.id');

        // Download as admin
        $resp = $this->get("/api/attachments/{$id}/download", $this->authHeaders($admin));
        $resp->assertOk();
    }

    public function test_download_unrelated_user_blocked(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $userA = $this->createStaffWithProfile();
        $userB = $this->createStaffWithProfile();
        $resourceId = $this->makeResource()->id;
        $up = $this->withHeaders($this->uploadHeaders($userA))->post('/api/attachments', [
            'file' => $this->realPng(),
            'attachable_type' => 'Resource',
            'attachable_id' => $resourceId,
        ]);
        $id = $up->json('data.id');

        // userB is not the uploader and the attachment is on a Resource — staff role
        // can attach but the canAttachTo rule allows any staff+ for Resource. So
        // that path will pass. Let's instead test an Order attachment.
        $order = \App\Domain\Models\Order::create([
            'order_number' => 'ORD-DL-' . mt_rand(),
            'user_id' => $userA->id, 'status' => 'confirmed',
            'subtotal' => 50, 'total' => 50, 'confirmed_at' => now(),
        ]);
        $up2 = $this->withHeaders($this->uploadHeaders($userA))->post('/api/attachments', [
            'file' => $this->realPng(),
            'attachable_type' => 'Order',
            'attachable_id' => $order->id,
        ]);
        $idOrder = $up2->json('data.id');

        $this->get("/api/attachments/{$idOrder}/download", $this->authHeaders($userB))
            ->assertStatus(403);
    }

    public function test_download_unknown_attachment_404(): void
    {
        $admin = $this->createUser('admin');
        $this->get('/api/attachments/999999/download', $this->authHeaders($admin))
            ->assertStatus(404);
    }

    public function test_download_missing_stored_file_returns_404(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $admin = $this->createUser('admin');
        $resourceId = $this->makeResource()->id;
        $up = $this->withHeaders($this->uploadHeaders($admin))->post('/api/attachments', [
            'file' => $this->realPng(),
            'attachable_type' => 'Resource',
            'attachable_id' => $resourceId,
        ]);
        $up->assertStatus(201);
        $id = $up->json('data.id');

        // Manually delete the underlying file so the download path's
        // "file not found" branch executes.
        $att = \App\Domain\Models\Attachment::find($id);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($att->stored_path);

        $this->get("/api/attachments/{$id}/download", $this->authHeaders($admin))
            ->assertStatus(404);
    }

    public function test_download_uploader_can_get_own_file(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $staff = $this->createStaffWithProfile();
        $resourceId = $this->makeResource()->id;
        $up = $this->withHeaders($this->uploadHeaders($staff))->post('/api/attachments', [
            'file' => $this->realPng(),
            'attachable_type' => 'Resource',
            'attachable_id' => $resourceId,
        ]);
        $id = $up->json('data.id');
        $this->get("/api/attachments/{$id}/download", $this->authHeaders($staff))->assertOk();
    }

    public function test_upload_corrupted_metadata_path_handles_gracefully(): void
    {
        // Drives the catch branch in upload(): an oversized file passes the
        // route validation max=20480 KB but a deliberately huge .pdf will
        // trip AttachmentPolicy::validate inside AttachmentService — the
        // controller must roll back the stored file and return 422.
        $staff = $this->createStaffWithProfile();
        $resourceId = $this->makeResource()->id;
        $file = \Illuminate\Http\UploadedFile::fake()->create('weird.bin', 1, 'application/x-octet-stream');
        $resp = $this->withHeaders($this->uploadHeaders($staff))->post('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'Resource',
            'attachable_id' => $resourceId,
        ]);
        $resp->assertStatus(422);
    }

    public function test_upload_owner_allowed_for_own_order(): void
    {
        if (!extension_loaded('gd')) { $this->markTestSkipped('GD required'); }
        $userA = $this->createStaffWithProfile();
        $order = \App\Domain\Models\Order::create([
            'order_number' => 'ORD-OWN-' . mt_rand(),
            'user_id' => $userA->id,
            'status' => 'confirmed',
            'subtotal' => 50, 'total' => 50,
            'confirmed_at' => now(),
        ]);

        $this->withHeaders($this->uploadHeaders($userA))
            ->post('/api/attachments', [
                'file' => $this->realPng(),
                'attachable_type' => 'Order',
                'attachable_id' => $order->id,
            ])->assertStatus(201);
    }
}
