<?php

namespace Database\Seeders;

use App\Domain\Models\Resource;
use App\Domain\Models\Role;
use App\Domain\Models\ServiceArea;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $swEng  = ServiceArea::where('slug', 'software-engineering')->first();
        $data   = ServiceArea::where('slug', 'data-analytics')->first();
        $cloud  = ServiceArea::where('slug', 'cloud-infrastructure')->first();

        $senior = Role::where('slug', 'senior')->first();
        $mid    = Role::where('slug', 'mid-level')->first();
        $lead   = Role::where('slug', 'lead')->first();

        $resources = [
            ['name' => 'Alice Chen',     'service_area_id' => $swEng->id, 'role_id' => $senior->id, 'capacity_hours' => 1800, 'is_available' => true],
            ['name' => 'Bob Martinez',   'service_area_id' => $swEng->id, 'role_id' => $mid->id,    'capacity_hours' => 2000, 'is_available' => true],
            ['name' => 'Carol Nguyen',   'service_area_id' => $data->id,  'role_id' => $lead->id,   'capacity_hours' => 1600, 'is_available' => true],
            ['name' => 'David Okafor',   'service_area_id' => $cloud->id, 'role_id' => $senior->id, 'capacity_hours' => 1900, 'is_available' => true],
            ['name' => 'Eve Thompson',   'service_area_id' => $cloud->id, 'role_id' => $mid->id,    'capacity_hours' => 2080, 'is_available' => false],
        ];

        foreach ($resources as $r) {
            Resource::firstOrCreate(
                ['name' => $r['name'], 'service_area_id' => $r['service_area_id']],
                $r,
            );
        }
    }
}
