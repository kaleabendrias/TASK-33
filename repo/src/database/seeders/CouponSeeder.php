<?php

namespace Database\Seeders;

use App\Domain\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['code' => 'WELCOME10',   'discount_type' => 'percentage', 'discount_value' => 10,  'min_order_amount' => 50,  'max_uses' => 100, 'valid_from' => '2025-01-01', 'valid_until' => '2026-12-31'],
            ['code' => 'FLAT25OFF',   'discount_type' => 'fixed',      'discount_value' => 25,  'min_order_amount' => 100, 'max_uses' => 50,  'valid_from' => '2025-01-01', 'valid_until' => '2026-12-31'],
            ['code' => 'LABWEEK20',   'discount_type' => 'percentage', 'discount_value' => 20,  'min_order_amount' => 200, 'max_uses' => 30,  'valid_from' => '2025-06-01', 'valid_until' => '2026-12-31'],
        ];

        foreach ($coupons as $c) {
            Coupon::firstOrCreate(['code' => $c['code']], $c);
        }
    }
}
