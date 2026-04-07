<?php

namespace Database\Seeders;

use App\Domain\Models\PricingBaseline;
use App\Domain\Models\Role;
use App\Domain\Models\ServiceArea;
use Illuminate\Database\Seeder;

class PricingBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $areas = ServiceArea::all()->keyBy('slug');
        $roles = Role::all()->keyBy('slug');

        // Base hourly rates per role level (USD)
        $baseRates = [
            'junior'    => 75.00,
            'mid-level' => 120.00,
            'senior'    => 175.00,
            'lead'      => 225.00,
            'principal' => 300.00,
        ];

        // Multiplier per service area (reflects market demand)
        $areaMultipliers = [
            'software-engineering' => 1.00,
            'data-analytics'      => 1.05,
            'cloud-infrastructure' => 1.15,
            'cybersecurity'       => 1.25,
            'ux-design'           => 0.95,
        ];

        foreach ($areas as $areaSlug => $area) {
            foreach ($roles as $roleSlug => $role) {
                $rate = round($baseRates[$roleSlug] * $areaMultipliers[$areaSlug], 2);

                PricingBaseline::firstOrCreate(
                    [
                        'service_area_id' => $area->id,
                        'role_id'         => $role->id,
                        'effective_from'  => '2025-01-01',
                    ],
                    [
                        'service_area_id' => $area->id,
                        'role_id'         => $role->id,
                        'hourly_rate'     => $rate,
                        'currency'        => 'USD',
                        'effective_from'  => '2025-01-01',
                        'effective_until' => null,
                    ],
                );
            }
        }
    }
}
