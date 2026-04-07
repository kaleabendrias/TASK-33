<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\{Attachment, AuditLog, BookableItem, ChangeHistory, Commission, Coupon, GroupLeaderAssignment, Order, OrderLineItem, Permission, PricingBaseline, Refund, Resource, Role, RolePermission, ServiceArea, Settlement, StaffProfile, StatusTransition, User, UserSession};
use UnitTests\TestCase;

class RelationshipsTest extends TestCase
{
    private User $user;
    private ServiceArea $sa;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'rel_user_' . mt_rand(), 'password' => 'TestPass@12345!', 'full_name' => 'Rel', 'role' => 'admin']);
        $this->sa = ServiceArea::create(['name' => 'SA ' . mt_rand(), 'slug' => 'sa-' . mt_rand()]);
        $this->role = Role::create(['name' => 'Role ' . mt_rand(), 'slug' => 'role-' . mt_rand(), 'level' => 1]);
    }

    public function test_service_area_resources(): void
    {
        Resource::create(['name' => 'R', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $this->assertCount(1, $this->sa->resources);
    }

    public function test_service_area_pricing_baselines(): void
    {
        PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => '2025-01-01']);
        $this->assertCount(1, $this->sa->pricingBaselines);
    }

    public function test_role_resources(): void
    {
        Resource::create(['name' => 'RR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $this->assertCount(1, $this->role->resources);
    }

    public function test_pricing_baseline_relations(): void
    {
        $pb = PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 75, 'effective_from' => '2025-01-01']);
        $this->assertNotNull($pb->serviceArea);
        $this->assertNotNull($pb->role);
    }

    public function test_resource_parent_children(): void
    {
        $parent = Resource::create(['name' => 'Parent', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $child = Resource::create(['name' => 'Child', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'parent_id' => $parent->id, 'capacity_hours' => 5]);
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertCount(1, $parent->children);
    }

    public function test_user_sessions_relation(): void
    {
        UserSession::create(['user_id' => $this->user->id, 'jti' => bin2hex(random_bytes(32)), 'issued_at' => now(), 'expires_at' => now()->addDay(), 'last_active_at' => now()]);
        $this->assertCount(1, $this->user->sessions);
        $this->assertCount(1, $this->user->activeSessions);
    }

    public function test_order_relations(): void
    {
        $item = BookableItem::create(['type' => 'room', 'name' => 'OR', 'daily_rate' => 100, 'tax_rate' => 0, 'capacity' => 1, 'is_active' => true]);
        $coupon = Coupon::create(['code' => 'REL' . mt_rand(), 'discount_type' => 'fixed', 'discount_value' => 5, 'valid_from' => now()->subDay(), 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'ORD-REL-' . mt_rand(), 'user_id' => $this->user->id, 'group_leader_id' => $this->user->id,
            'service_area_id' => $this->sa->id, 'status' => 'confirmed', 'subtotal' => 100, 'total' => 95,
            'coupon_id' => $coupon->id, 'confirmed_at' => now(),
        ]);
        OrderLineItem::create(['order_id' => $order->id, 'bookable_item_id' => $item->id, 'booking_date' => '2026-01-01', 'quantity' => 1, 'unit_price' => 100, 'line_subtotal' => 100, 'line_tax' => 0, 'line_total' => 100]);
        Refund::create(['order_id' => $order->id, 'original_amount' => 95, 'cancellation_fee' => 0, 'refund_amount' => 95, 'is_full_refund' => true, 'staff_unavailable_override' => false, 'status' => 'processed']);

        $this->assertNotNull($order->user);
        $this->assertNotNull($order->groupLeader);
        $this->assertNotNull($order->serviceArea);
        $this->assertNotNull($order->coupon);
        $this->assertCount(1, $order->lineItems);
        $this->assertCount(1, $order->refunds);
    }

    public function test_order_line_item_relations(): void
    {
        $item = BookableItem::create(['type' => 'room', 'name' => 'LI', 'daily_rate' => 50, 'tax_rate' => 0, 'capacity' => 1, 'is_active' => true]);
        $order = Order::create(['order_number' => 'ORD-LI-' . mt_rand(), 'user_id' => $this->user->id, 'status' => 'draft', 'subtotal' => 50, 'total' => 50]);
        $li = OrderLineItem::create(['order_id' => $order->id, 'bookable_item_id' => $item->id, 'booking_date' => '2026-01-01', 'quantity' => 1, 'unit_price' => 50, 'line_subtotal' => 50, 'line_tax' => 0, 'line_total' => 50]);
        $this->assertNotNull($li->order);
        $this->assertNotNull($li->bookableItem);
    }

    public function test_refund_order_relation(): void
    {
        $order = Order::create(['order_number' => 'ORD-RF-' . mt_rand(), 'user_id' => $this->user->id, 'status' => 'refunded', 'subtotal' => 100, 'total' => 100]);
        $refund = Refund::create(['order_id' => $order->id, 'original_amount' => 100, 'cancellation_fee' => 20, 'refund_amount' => 80, 'is_full_refund' => false, 'staff_unavailable_override' => false, 'status' => 'processed']);
        $this->assertEquals($order->id, $refund->order->id);
    }

    public function test_settlement_commissions(): void
    {
        $settlement = Settlement::create(['reference' => 'STL-REL-' . mt_rand(), 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'gross_amount' => 1000, 'refund_total' => 50, 'net_amount' => 950]);
        Commission::create(['group_leader_id' => $this->user->id, 'settlement_id' => $settlement->id, 'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-15', 'attributed_revenue' => 500, 'commission_amount' => 50]);
        $this->assertCount(1, $settlement->commissions);
        $this->assertNotNull($settlement->commissions->first()->groupLeader);
    }

    public function test_attachment_polymorphic(): void
    {
        $resource = Resource::create(['name' => 'AR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        Attachment::create([
            'attachable_type' => Resource::class, 'attachable_id' => $resource->id,
            'original_filename' => 'test.pdf', 'stored_path' => '/tmp/test.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1024, 'sha256_fingerprint' => hash('sha256', 'test'),
            'uploaded_by' => $this->user->id,
        ]);
        $this->assertCount(1, $resource->attachments);
    }

    public function test_status_transition_relations(): void
    {
        $resource = Resource::create(['name' => 'ST', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $resource->transitionTo('reserved', 'Test');
        $transition = StatusTransition::where('transitionable_id', $resource->id)->first();
        $this->assertNotNull($transition->transitionable);
    }

    public function test_change_history_relations(): void
    {
        $resource = Resource::create(['name' => 'CH', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $resource->update(['name' => 'Changed']);
        $change = ChangeHistory::where('trackable_id', $resource->id)->first();
        $this->assertNotNull($change);
        $this->assertNotNull($change->trackable);
    }

    public function test_audit_log_actor(): void
    {
        AuditLog::create(['action' => 'test', 'actor_id' => $this->user->id, 'actor_role' => 'admin', 'created_at' => now()]);
        $log = AuditLog::first();
        $this->assertNotNull($log->actor);
    }

    public function test_staff_profile_user(): void
    {
        $profile = StaffProfile::create(['user_id' => $this->user->id, 'employee_id' => 'E001', 'department' => 'Eng', 'title' => 'Dev']);
        $this->assertNotNull($profile->user);
    }

    public function test_group_leader_assignment_relations(): void
    {
        $gla = GroupLeaderAssignment::create(['user_id' => $this->user->id, 'service_area_id' => $this->sa->id, 'location' => 'B1']);
        $this->assertNotNull($gla->user);
        $this->assertNotNull($gla->serviceArea);
    }

    public function test_bookable_item_relations(): void
    {
        $item = BookableItem::create(['type' => 'room', 'name' => 'BI', 'daily_rate' => 100, 'tax_rate' => 0, 'capacity' => 1, 'is_active' => true, 'service_area_id' => $this->sa->id]);
        $this->assertNotNull($item->serviceArea);
    }

    public function test_role_permission_relation(): void
    {
        $perm = Permission::create(['slug' => 'test.perm.' . mt_rand()]);
        $rp = RolePermission::create(['role' => 'staff', 'permission_id' => $perm->id]);
        $this->assertNotNull($rp->permission);
    }

    public function test_commission_relations(): void
    {
        $c = Commission::create(['group_leader_id' => $this->user->id, 'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-15', 'attributed_revenue' => 100, 'commission_amount' => 10]);
        $this->assertNotNull($c->groupLeader);
    }

    public function test_settlement_finalizer(): void
    {
        $s = Settlement::create(['reference' => 'STL-FIN-' . mt_rand(), 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'gross_amount' => 100, 'net_amount' => 100, 'finalized_by' => $this->user->id]);
        $this->assertNotNull($s->finalizer);
    }

    public function test_attachment_uploader(): void
    {
        $a = Attachment::create([
            'attachable_type' => 'Test', 'attachable_id' => 1,
            'original_filename' => 'a.pdf', 'stored_path' => '/a', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256_fingerprint' => hash('sha256', 'x'), 'uploaded_by' => $this->user->id,
        ]);
        $this->assertNotNull($a->uploader);
    }
}
