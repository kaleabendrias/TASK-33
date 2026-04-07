<?php

namespace ApiTests\Security;

use App\Domain\Models\Attachment;
use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\OrderLineItem;
use App\Domain\Models\Settlement;
use App\Domain\Models\StaffProfile;
use ApiTests\TestCase;

class IdorAndIsolationTest extends TestCase
{
    // ── Attachment IDOR ─────────────────────────────────────────────

    public function test_attachment_download_denied_for_unrelated_user(): void
    {
        $owner = $this->createUser('user', ['username' => 'att_owner_' . mt_rand()]);
        $other = $this->createUser('user', ['username' => 'att_other_' . mt_rand()]);

        $attachment = Attachment::create([
            'attachable_type' => 'Resource', 'attachable_id' => 1,
            'original_filename' => 'secret.pdf', 'stored_path' => 'attachments/fake.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1024,
            'sha256_fingerprint' => hash('sha256', 'secret'),
            'uploaded_by' => $owner->id,
        ]);

        $this->getJson("/api/attachments/{$attachment->id}/download", $this->authHeaders($other))
            ->assertStatus(403);
    }

    public function test_attachment_download_allowed_for_uploader(): void
    {
        $owner = $this->createUser('user', ['username' => 'att_self_' . mt_rand()]);
        $attachment = Attachment::create([
            'attachable_type' => 'Resource', 'attachable_id' => 1,
            'original_filename' => 'mine.pdf', 'stored_path' => 'attachments/fake.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1024,
            'sha256_fingerprint' => hash('sha256', 'mine'),
            'uploaded_by' => $owner->id,
        ]);

        // Will 404 because file doesn't exist on disk, but NOT 403
        $this->getJson("/api/attachments/{$attachment->id}/download", $this->authHeaders($owner))
            ->assertStatus(404); // authorized but file missing from disk
    }

    public function test_attachment_download_allowed_for_admin(): void
    {
        $owner = $this->createUser('user', ['username' => 'att_adm_o_' . mt_rand()]);
        $admin = $this->createUser('admin', ['username' => 'att_adm_a_' . mt_rand()]);
        $attachment = Attachment::create([
            'attachable_type' => 'Resource', 'attachable_id' => 1,
            'original_filename' => 'any.pdf', 'stored_path' => 'attachments/fake.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1024,
            'sha256_fingerprint' => hash('sha256', 'any'),
            'uploaded_by' => $owner->id,
        ]);

        $this->getJson("/api/attachments/{$attachment->id}/download", $this->authHeaders($admin))
            ->assertStatus(404); // authorized, disk file missing is 404 not 403
    }

    public function test_attachment_on_order_allowed_for_order_owner(): void
    {
        $orderOwner = $this->createUser('user', ['username' => 'att_oo_' . mt_rand()]);
        $uploader = $this->createUser('staff', ['username' => 'att_up_' . mt_rand()]);

        $order = Order::create([
            'order_number' => 'ORD-ATT-' . mt_rand(), 'user_id' => $orderOwner->id,
            'status' => 'confirmed', 'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);

        $attachment = Attachment::create([
            'attachable_type' => \App\Domain\Models\Order::class, 'attachable_id' => $order->id,
            'original_filename' => 'receipt.pdf', 'stored_path' => 'attachments/fake.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 512,
            'sha256_fingerprint' => hash('sha256', 'receipt'),
            'uploaded_by' => $uploader->id,
        ]);

        // Order owner can download even though they didn't upload
        $this->getJson("/api/attachments/{$attachment->id}/download", $this->authHeaders($orderOwner))
            ->assertStatus(404); // 404 = authorized, file just doesn't exist on disk
    }

    // ── Settlement data isolation ───────────────────────────────────

    public function test_group_leader_sees_only_own_settlements(): void
    {
        $gl1 = $this->createStaffWithProfile('group-leader', ['username' => 'gl_iso1_' . mt_rand()]);
        $gl2 = $this->createStaffWithProfile('group-leader', ['username' => 'gl_iso2_' . mt_rand()]);

        $stl1 = Settlement::create([
            'reference' => 'STL-ISO-' . mt_rand(), 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 1000, 'refund_total' => 0, 'net_amount' => 1000,
        ]);
        Commission::create([
            'group_leader_id' => $gl1->id, 'settlement_id' => $stl1->id,
            'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-15',
            'attributed_revenue' => 500, 'commission_amount' => 50,
        ]);

        $stl2 = Settlement::create([
            'reference' => 'STL-ISO2-' . mt_rand(), 'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
            'gross_amount' => 2000, 'refund_total' => 0, 'net_amount' => 2000,
        ]);
        Commission::create([
            'group_leader_id' => $gl2->id, 'settlement_id' => $stl2->id,
            'cycle_start' => '2026-02-01', 'cycle_end' => '2026-02-15',
            'attributed_revenue' => 800, 'commission_amount' => 80,
        ]);

        // GL1 should only see their settlement
        $resp = $this->getJson('/api/settlements', $this->authHeaders($gl1));
        $resp->assertOk();
        $data = $resp->json('data');
        $refs = collect($data)->pluck('reference')->toArray();
        $this->assertContains($stl1->reference, $refs);
        $this->assertNotContains($stl2->reference, $refs);
    }

    public function test_group_leader_sees_only_own_commissions(): void
    {
        $gl1 = $this->createStaffWithProfile('group-leader', ['username' => 'glc1_' . mt_rand()]);
        $gl2 = $this->createStaffWithProfile('group-leader', ['username' => 'glc2_' . mt_rand()]);

        Commission::create(['group_leader_id' => $gl1->id, 'cycle_start' => '2026-03-01', 'cycle_end' => '2026-03-15', 'attributed_revenue' => 100, 'commission_amount' => 10]);
        Commission::create(['group_leader_id' => $gl2->id, 'cycle_start' => '2026-03-01', 'cycle_end' => '2026-03-15', 'attributed_revenue' => 200, 'commission_amount' => 20]);

        $resp = $this->getJson('/api/commissions', $this->authHeaders($gl1));
        $resp->assertOk();
        $data = $resp->json('data');
        foreach ($data as $c) {
            $this->assertEquals($gl1->id, $c['group_leader_id']);
        }
    }

    public function test_admin_sees_all_settlements(): void
    {
        $admin = $this->createUser('admin');
        Settlement::create(['reference' => 'STL-ADM1-' . mt_rand(), 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'gross_amount' => 100, 'net_amount' => 100]);
        Settlement::create(['reference' => 'STL-ADM2-' . mt_rand(), 'period_start' => '2026-02-01', 'period_end' => '2026-02-28', 'gross_amount' => 200, 'net_amount' => 200]);

        $resp = $this->getJson('/api/settlements', $this->authHeaders($admin));
        // Admin route is under /admin prefix; for the group-leader route, admin has the role
        // Actually admin is group-leader+ so they can access /api/settlements
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(2, count($resp->json('data')));
    }

    // ── Export data isolation ────────────────────────────────────────

    public function test_order_export_scoped_to_user(): void
    {
        $user1 = $this->createUser('user', ['username' => 'exp_u1_' . mt_rand()]);
        $user2 = $this->createUser('user', ['username' => 'exp_u2_' . mt_rand()]);

        $item = BookableItem::create(['type' => 'room', 'name' => 'ExpRoom', 'daily_rate' => 50, 'tax_rate' => 0, 'capacity' => 10, 'is_active' => true]);
        Order::create(['order_number' => 'ORD-EXP1-' . mt_rand(), 'user_id' => $user1->id, 'status' => 'confirmed', 'subtotal' => 50, 'total' => 50, 'confirmed_at' => now()]);
        Order::create(['order_number' => 'ORD-EXP2-' . mt_rand(), 'user_id' => $user2->id, 'status' => 'confirmed', 'subtotal' => 75, 'total' => 75, 'confirmed_at' => now()]);

        $resp = $this->postJson('/api/exports', [
            'type' => 'orders', 'format' => 'csv',
            'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        ], $this->authHeaders($user1));

        $resp->assertOk();
        $content = $resp->streamedContent();
        // User1's export should contain their order but not user2's
        $this->assertStringContainsString('EXP1', $content);
        $this->assertStringNotContainsString('EXP2', $content);
    }

    // ── Refresh endpoint sanitization ───────────────────────────────

    public function test_refresh_endpoint_returns_generic_error(): void
    {
        $resp = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer invalid.garbage.token',
            'Accept' => 'application/json',
        ]);
        $resp->assertStatus(401);
        $this->assertEquals('Unable to refresh token.', $resp->json('message'));
    }

    // ── Regular user can create and cancel own orders ────────────────

    public function test_regular_user_can_create_order(): void
    {
        $user = $this->createUser('user');
        $item = BookableItem::create(['type' => 'room', 'name' => 'UserRoom', 'daily_rate' => 100, 'tax_rate' => 0, 'capacity' => 5, 'is_active' => true]);

        $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $item->id, 'booking_date' => '2026-09-01', 'quantity' => 1]],
        ], $this->authHeaders($user))->assertStatus(201);
    }

    public function test_regular_user_can_cancel_own_order(): void
    {
        $user = $this->createUser('user');
        $item = BookableItem::create(['type' => 'room', 'name' => 'CancelRoom', 'daily_rate' => 80, 'tax_rate' => 0, 'capacity' => 5, 'is_active' => true]);

        $order = Order::create([
            'order_number' => 'ORD-UC-' . mt_rand(), 'user_id' => $user->id,
            'status' => 'confirmed', 'subtotal' => 80, 'total' => 80, 'confirmed_at' => now(),
        ]);
        OrderLineItem::create([
            'order_id' => $order->id, 'bookable_item_id' => $item->id,
            'booking_date' => '2026-09-02', 'quantity' => 1, 'unit_price' => 80,
            'line_subtotal' => 80, 'line_tax' => 0, 'line_total' => 80,
        ]);

        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'cancelled'], $this->authHeaders($user))
            ->assertOk();
    }

    public function test_regular_user_cannot_cancel_other_users_order(): void
    {
        $owner = $this->createUser('user', ['username' => 'idor_own_' . mt_rand()]);
        $attacker = $this->createUser('user', ['username' => 'idor_atk_' . mt_rand()]);

        $order = Order::create([
            'order_number' => 'ORD-IDOR-' . mt_rand(), 'user_id' => $owner->id,
            'status' => 'confirmed', 'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);

        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'cancelled'], $this->authHeaders($attacker))
            ->assertStatus(403);
    }
}
