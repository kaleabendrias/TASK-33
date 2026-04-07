<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\{Attachment, ChangeHistory, Resource, Role, ServiceArea, StatusTransition, User};
use UnitTests\TestCase;

class MorphRelationsTest extends TestCase
{
    private ServiceArea $sa;
    private Role $role;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'morph_' . mt_rand(), 'password' => 'TestPass@12345!', 'full_name' => 'M', 'role' => 'admin']);
        $this->sa = ServiceArea::create(['name' => 'MSA', 'slug' => 'msa-' . mt_rand()]);
        $this->role = Role::create(['name' => 'MR', 'slug' => 'mr-' . mt_rand(), 'level' => 1]);
    }

    public function test_attachment_attachable_morph(): void
    {
        $resource = Resource::create(['name' => 'AM', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $attachment = Attachment::create([
            'attachable_type' => Resource::class, 'attachable_id' => $resource->id,
            'original_filename' => 'f.pdf', 'stored_path' => '/f',
            'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'sha256_fingerprint' => hash('sha256', 'f'), 'uploaded_by' => $this->user->id,
        ]);
        $this->assertInstanceOf(Resource::class, $attachment->attachable);
        $this->assertNotNull($attachment->uploader);
    }

    public function test_change_history_trackable_morph(): void
    {
        $resource = Resource::create(['name' => 'CHM', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $resource->update(['name' => 'Changed']);
        $ch = ChangeHistory::where('trackable_id', $resource->id)->first();
        $this->assertNotNull($ch);
        $morph = $ch->trackable;
        $this->assertInstanceOf(Resource::class, $morph);
        // changedBy may be null since no auth_user on request in test
    }

    public function test_status_transition_transitionable_morph(): void
    {
        $resource = Resource::create(['name' => 'STM', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $resource->transitionTo('reserved', 'Test');
        $st = StatusTransition::where('transitionable_id', $resource->id)->first();
        $this->assertNotNull($st);
        $this->assertInstanceOf(Resource::class, $st->transitionable);
        // transitionedBy test
        $this->assertNull($st->transitionedBy); // No auth user in test
    }

    public function test_change_history_changed_by_null(): void
    {
        $resource = Resource::create(['name' => 'CHN', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $resource->update(['capacity_hours' => 20]);
        $ch = ChangeHistory::where('trackable_id', $resource->id)->where('field_name', 'capacity_hours')->first();
        $this->assertNotNull($ch);
        $this->assertNull($ch->changedBy);
    }

    public function test_commission_casts_and_settlement(): void
    {
        $commission = \App\Domain\Models\Commission::create([
            'group_leader_id' => $this->user->id,
            'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-15',
            'attributed_revenue' => 500, 'commission_rate' => 0.10,
            'commission_amount' => 50, 'status' => 'held',
        ]);
        $this->assertNull($commission->settlement);
    }

    public function test_bookable_item_line_items(): void
    {
        $item = \App\Domain\Models\BookableItem::create(['type' => 'room', 'name' => 'LIR', 'daily_rate' => 50, 'tax_rate' => 0, 'capacity' => 1, 'is_active' => true]);
        $this->assertCount(0, $item->lineItems);
    }

    public function test_bookable_item_full_day_availability(): void
    {
        $item = \App\Domain\Models\BookableItem::create(['type' => 'room', 'name' => 'FD', 'daily_rate' => 50, 'tax_rate' => 0, 'capacity' => 1, 'is_active' => true]);
        // Full day (no start/end time)
        $this->assertTrue($item->hasAvailability('2026-09-01'));
    }
}
