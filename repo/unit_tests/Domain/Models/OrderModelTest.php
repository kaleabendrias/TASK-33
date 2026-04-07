<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\Order;
use App\Domain\Models\User;
use UnitTests\TestCase;

class OrderModelTest extends TestCase
{
    public function test_generate_order_number_format(): void
    {
        $num = Order::generateOrderNumber();
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $num);
    }

    public function test_within_full_refund_window(): void
    {
        $user = User::create(['username' => 'rfnd', 'password' => 'TestPass@12345!', 'full_name' => 'R', 'role' => 'user']);
        $order = Order::create([
            'order_number' => 'ORD-RFND-0001', 'user_id' => $user->id, 'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);
        $this->assertTrue($order->isWithinFullRefundWindow());
    }

    public function test_outside_full_refund_window(): void
    {
        $user = User::create(['username' => 'rfnd2', 'password' => 'TestPass@12345!', 'full_name' => 'R', 'role' => 'user']);
        $order = Order::create([
            'order_number' => 'ORD-RFND-0002', 'user_id' => $user->id, 'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'confirmed_at' => now()->subMinutes(16),
        ]);
        $this->assertFalse($order->isWithinFullRefundWindow());
    }

    public function test_refund_window_null_confirmed_at(): void
    {
        $user = User::create(['username' => 'rfnd3', 'password' => 'TestPass@12345!', 'full_name' => 'R', 'role' => 'user']);
        $order = Order::create([
            'order_number' => 'ORD-RFND-0003', 'user_id' => $user->id, 'status' => 'draft',
            'subtotal' => 100, 'total' => 100,
        ]);
        $this->assertFalse($order->isWithinFullRefundWindow());
    }
}
