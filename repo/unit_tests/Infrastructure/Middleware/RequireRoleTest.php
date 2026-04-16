<?php

namespace UnitTests\Infrastructure\Middleware;

use App\Api\Middleware\RequireRole;
use App\Domain\Models\User;
use Illuminate\Http\Request;
use UnitTests\TestCase;

class RequireRoleTest extends TestCase
{
    private RequireRole $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RequireRole();
    }

    private function next(): \Closure
    {
        return fn($req) => response()->json(['ok' => true], 200);
    }

    private function jsonRequest(?User $user = null): Request
    {
        $request = Request::create('/api/test');
        $request->headers->set('Accept', 'application/json');
        if ($user) {
            $request->attributes->set('auth_user', $user);
        }
        return $request;
    }

    public function test_rejects_unauthenticated_request_with_401(): void
    {
        $response = $this->middleware->handle($this->jsonRequest(), $this->next(), 'admin');
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_rejects_insufficient_role_with_403(): void
    {
        $user = $this->createUser('user');
        $response = $this->middleware->handle($this->jsonRequest($user), $this->next(), 'admin');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_403_response_contains_message_field(): void
    {
        $user = $this->createUser('user');
        $response = $this->middleware->handle($this->jsonRequest($user), $this->next(), 'admin');
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_allows_exact_role_match(): void
    {
        $user = $this->createUser('admin');
        $response = $this->middleware->handle($this->jsonRequest($user), $this->next(), 'admin');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_higher_role_than_minimum(): void
    {
        $admin = $this->createUser('admin');
        $response = $this->middleware->handle($this->jsonRequest($admin), $this->next(), 'user');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_staff_blocked_from_admin_route(): void
    {
        $staff = $this->createUser('staff');
        $response = $this->middleware->handle($this->jsonRequest($staff), $this->next(), 'admin');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_group_leader_blocked_from_admin_route(): void
    {
        $leader = $this->createUser('group-leader');
        $response = $this->middleware->handle($this->jsonRequest($leader), $this->next(), 'admin');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_staff_allowed_on_staff_route(): void
    {
        $staff = $this->createUser('staff');
        $response = $this->middleware->handle($this->jsonRequest($staff), $this->next(), 'staff');
        $this->assertEquals(200, $response->getStatusCode());
    }
}
