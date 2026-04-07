<?php

namespace UnitTests\Infrastructure\Repositories;

use App\Domain\Models\AuditLog;
use App\Domain\Models\User;
use App\Infrastructure\Repositories\EloquentAuditLogRepository;
use UnitTests\TestCase;

class AuditLogRepositoryTest extends TestCase
{
    private EloquentAuditLogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentAuditLogRepository();
    }

    public function test_log_creates_entry(): void
    {
        $this->repo->log('test_action', 'User', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'test_action']);
    }

    public function test_log_with_values(): void
    {
        $this->repo->log('update', 'Order', 42, ['status' => 'draft'], ['status' => 'confirmed'], ['ip' => '127.0.0.1']);
        $log = AuditLog::where('action', 'update')->first();
        $this->assertEquals(['status' => 'draft'], $log->old_values);
        $this->assertEquals(['status' => 'confirmed'], $log->new_values);
        $this->assertEquals(['ip' => '127.0.0.1'], $log->metadata);
    }

    public function test_paginate_default(): void
    {
        for ($i = 0; $i < 30; $i++) {
            AuditLog::create(['action' => "action_{$i}", 'created_at' => now()]);
        }
        $result = $this->repo->paginate([], 10);
        $this->assertCount(10, $result->items());
        $this->assertEquals(30, $result->total());
    }

    public function test_paginate_filter_by_action(): void
    {
        AuditLog::create(['action' => 'login', 'created_at' => now()]);
        AuditLog::create(['action' => 'logout', 'created_at' => now()]);
        $result = $this->repo->paginate(['action' => 'login']);
        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_filter_by_entity(): void
    {
        AuditLog::create(['action' => 'test', 'entity_type' => 'User', 'entity_id' => 5, 'created_at' => now()]);
        AuditLog::create(['action' => 'test', 'entity_type' => 'Order', 'created_at' => now()]);
        $result = $this->repo->paginate(['entity_type' => 'User', 'entity_id' => 5]);
        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_filter_by_actor(): void
    {
        $user = User::create(['username' => 'aud_actor', 'password' => 'TestPass@12345!', 'full_name' => 'A', 'role' => 'admin']);
        AuditLog::create(['action' => 'test', 'actor_id' => $user->id, 'created_at' => now()]);
        $result = $this->repo->paginate(['actor_id' => $user->id]);
        $this->assertEquals(1, $result->total());
    }

    public function test_paginate_filter_by_date_range(): void
    {
        AuditLog::create(['action' => 'old', 'created_at' => '2025-01-01 00:00:00']);
        AuditLog::create(['action' => 'new', 'created_at' => '2026-06-15 00:00:00']);
        $result = $this->repo->paginate(['from' => '2026-01-01', 'to' => '2026-12-31']);
        $this->assertEquals(1, $result->total());
    }
}
