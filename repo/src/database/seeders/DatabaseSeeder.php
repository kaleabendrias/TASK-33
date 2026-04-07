<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ServiceAreaSeeder::class,
            RoleSeeder::class,
            ResourceSeeder::class,
            PricingBaselineSeeder::class,
            UserSeeder::class,
            PermissionSeeder::class,
            BookableItemSeeder::class,
            CouponSeeder::class,
            GroupLeaderAssignmentSeeder::class,
        ]);
    }
}
