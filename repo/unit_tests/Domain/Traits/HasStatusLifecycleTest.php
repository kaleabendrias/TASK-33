<?php

namespace UnitTests\Domain\Traits;

use App\Domain\Models\Resource;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\Role;
use App\Domain\Models\StatusTransition;
use UnitTests\TestCase;

class HasStatusLifecycleTest extends TestCase
{
    private function makeResource(string $status = 'available'): Resource
    {
        $sa = ServiceArea::create(['name' => 'Test SA ' . mt_rand(), 'slug' => 'sa-' . mt_rand()]);
        $role = Role::create(['name' => 'TestRole ' . mt_rand(), 'slug' => 'tr-' . mt_rand(), 'level' => 1]);
        return Resource::create([
            'name' => 'R-' . mt_rand(), 'service_area_id' => $sa->id, 'role_id' => $role->id,
            'capacity_hours' => 100, 'is_available' => true, 'status' => $status,
        ]);
    }

    public function test_can_transition_to_valid(): void
    {
        $r = $this->makeResource('available');
        $this->assertTrue($r->canTransitionTo('reserved'));
        $this->assertTrue($r->canTransitionTo('decommissioned'));
    }

    public function test_cannot_transition_to_invalid(): void
    {
        $r = $this->makeResource('available');
        // available->in_use is invalid; must go through reserved first
        $this->assertFalse($r->canTransitionTo('in_use'));
    }

    public function test_transition_to_creates_record(): void
    {
        $r = $this->makeResource('available');
        $r->transitionTo('reserved', 'Approved');
        $this->assertEquals('reserved', $r->status);
        $this->assertCount(1, StatusTransition::where('transitionable_id', $r->id)->get());
    }

    public function test_transition_to_invalid_throws(): void
    {
        $r = $this->makeResource('available');
        $this->expectException(\DomainException::class);
        $r->transitionTo('in_use'); // available->in_use is invalid
    }

    public function test_full_lifecycle(): void
    {
        $r = $this->makeResource('available');
        $r->transitionTo('reserved');
        $r->transitionTo('maintenance', 'Review');
        $r->transitionTo('decommissioned', 'Done');
        $this->assertEquals('decommissioned', $r->status);
        $this->assertCount(3, $r->statusTransitions);
    }

    public function test_allowed_transitions_map(): void
    {
        $map = Resource::allowedTransitions();
        $this->assertArrayHasKey('available', $map);
        $this->assertArrayHasKey('reserved', $map);
        $this->assertArrayHasKey('maintenance', $map);
        $this->assertArrayHasKey('decommissioned', $map);
        $this->assertContains('reserved', $map['available']);
    }
}
