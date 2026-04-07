<?php

namespace UnitTests\Domain\Policies;

use App\Domain\Policies\ResourcePolicy;
use PHPUnit\Framework\TestCase;

class ResourcePolicyTest extends TestCase
{
    public function test_valid_capacity(): void
    {
        $this->assertTrue(ResourcePolicy::isWithinCapacityLimit(1.0));
        $this->assertTrue(ResourcePolicy::isWithinCapacityLimit(2080.0));
        $this->assertTrue(ResourcePolicy::isWithinCapacityLimit(1000.0));
    }

    public function test_zero_capacity_invalid(): void
    {
        $this->assertFalse(ResourcePolicy::isWithinCapacityLimit(0));
    }

    public function test_negative_capacity_invalid(): void
    {
        $this->assertFalse(ResourcePolicy::isWithinCapacityLimit(-1));
    }

    public function test_over_max_capacity_invalid(): void
    {
        $this->assertFalse(ResourcePolicy::isWithinCapacityLimit(2080.01));
    }

    public function test_max_capacity_constant(): void
    {
        $this->assertEquals(2080.0, ResourcePolicy::MAX_CAPACITY_HOURS);
    }

    public function test_can_be_assigned_with_available_resource(): void
    {
        $resource = new \App\Domain\Models\Resource();
        $resource->is_available = true;
        $resource->capacity_hours = 100;
        $this->assertTrue(ResourcePolicy::canBeAssigned($resource));
    }

    public function test_cannot_be_assigned_when_unavailable(): void
    {
        $resource = new \App\Domain\Models\Resource();
        $resource->is_available = false;
        $resource->capacity_hours = 100;
        $this->assertFalse(ResourcePolicy::canBeAssigned($resource));
    }

    public function test_cannot_be_assigned_with_zero_capacity(): void
    {
        $resource = new \App\Domain\Models\Resource();
        $resource->is_available = true;
        $resource->capacity_hours = 0;
        $this->assertFalse(ResourcePolicy::canBeAssigned($resource));
    }
}
