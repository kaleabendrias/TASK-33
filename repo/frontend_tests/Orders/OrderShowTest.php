<?php

namespace FrontendTests\Orders;

use App\Domain\Models\Order;
use App\Livewire\Orders\OrderShow;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for OrderShow.
 *
 * Covers: default property state and rendering for the order owner.
 * Authorization parity (other users blocked, staff check-in/out/complete)
 * belongs in API_tests/Livewire/LivewireAuthorizationTest.php.
 */
class OrderShowTest extends TestCase
{
    private function makeOrder(int $userId, string $status = 'confirmed'): Order
    {
        return Order::create([
            'order_number' => 'FE-' . mt_rand(),
            'user_id' => $userId,
            'status' => $status,
            'subtotal' => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);
    }

    public function test_default_cancel_reason_is_empty(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u->id);
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(OrderShow::class, ['orderId' => $order->id])->get('cancelReason'));
    }

    public function test_default_error_is_empty(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u->id);
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(OrderShow::class, ['orderId' => $order->id])->get('error'));
    }

    public function test_order_id_is_set_on_mount(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u->id);
        $this->actAs($u);
        $this->assertEquals($order->id, Livewire::test(OrderShow::class, ['orderId' => $order->id])->get('orderId'));
    }

    public function test_component_renders_for_order_owner(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u->id);
        $this->actAs($u);
        Livewire::test(OrderShow::class, ['orderId' => $order->id])->assertOk();
    }

    public function test_cancel_reason_property_binding(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u->id);
        $this->actAs($u);
        Livewire::test(OrderShow::class, ['orderId' => $order->id])
            ->set('cancelReason', 'changed mind')
            ->assertSet('cancelReason', 'changed mind');
    }
}
