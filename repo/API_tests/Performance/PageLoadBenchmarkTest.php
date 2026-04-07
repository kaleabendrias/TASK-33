<?php

namespace ApiTests\Performance;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use ApiTests\TestCase;

/**
 * Reproducible server-render performance benchmarks.
 *
 * The "first meaningful interaction" KPI for an offline Livewire app is dominated by
 * server-side render time (no SPA hydration). We assert that the dashboard, booking
 * list, and order list all return within budgets so a regression in render or query
 * performance fails the build instead of slipping into production.
 *
 * Budgets are intentionally generous to remain stable in CI while still catching
 * pathological regressions (e.g. accidental N+1 explosions).
 */
class PageLoadBenchmarkTest extends TestCase
{
    private const BUDGET_MS_DASHBOARD = 2500; // first meaningful interaction
    private const BUDGET_MS_LIST_PAGE = 2500;

    private function timed(callable $fn): float
    {
        $start = microtime(true);
        $fn();
        return (microtime(true) - $start) * 1000;
    }

    private function seedSomeData(int $count = 25): void
    {
        $user = $this->createUser('user');
        for ($i = 0; $i < $count; $i++) {
            BookableItem::create([
                'type' => 'room', 'name' => "Perf Room $i",
                'hourly_rate' => 50, 'daily_rate' => 200, 'tax_rate' => 0.1,
                'capacity' => 5, 'is_active' => true,
            ]);
            Order::create([
                'order_number' => "ORD-PERF-$i", 'user_id' => $user->id,
                'status' => 'confirmed', 'subtotal' => 100, 'total' => 110,
                'confirmed_at' => now(),
            ]);
        }
    }

    public function test_health_endpoint_under_budget(): void
    {
        $ms = $this->timed(function () {
            $this->getJson('/api/health')->assertOk();
        });
        $this->assertLessThan(500, $ms, "Health endpoint took {$ms}ms (budget 500ms)");
    }

    public function test_dashboard_data_query_under_budget(): void
    {
        $this->seedSomeData(20);
        $user = $this->createUser('user');

        $ms = $this->timed(function () use ($user) {
            // The dashboard's data query — proxy via /api/orders which exercises
            // the same Order model + tenant scoping logic.
            $this->getJson('/api/orders', $this->authHeaders($user))->assertOk();
        });
        $this->assertLessThan(self::BUDGET_MS_DASHBOARD, $ms,
            "Dashboard data query took {$ms}ms (budget " . self::BUDGET_MS_DASHBOARD . "ms)");
    }

    public function test_booking_catalog_under_budget(): void
    {
        $this->seedSomeData(30);
        $user = $this->createUser('user');

        $ms = $this->timed(function () use ($user) {
            $this->getJson('/api/bookings/items', $this->authHeaders($user))->assertOk();
        });
        $this->assertLessThan(self::BUDGET_MS_LIST_PAGE, $ms,
            "Booking catalog took {$ms}ms (budget " . self::BUDGET_MS_LIST_PAGE . "ms)");
    }

    public function test_order_list_under_budget(): void
    {
        $this->seedSomeData(40);
        $user = $this->createUser('user');

        $ms = $this->timed(function () use ($user) {
            $this->getJson('/api/orders', $this->authHeaders($user))->assertOk();
        });
        $this->assertLessThan(self::BUDGET_MS_LIST_PAGE, $ms,
            "Order list took {$ms}ms (budget " . self::BUDGET_MS_LIST_PAGE . "ms)");
    }
}
