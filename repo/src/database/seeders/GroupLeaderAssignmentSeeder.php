<?php

namespace Database\Seeders;

use App\Domain\Models\GroupLeaderAssignment;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\User;
use Illuminate\Database\Seeder;

class GroupLeaderAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $gl = User::where('username', 'groupleader')->first();
        if (!$gl) return;

        $swEng = ServiceArea::where('slug', 'software-engineering')->first();
        $data  = ServiceArea::where('slug', 'data-analytics')->first();

        if ($swEng) {
            GroupLeaderAssignment::firstOrCreate(
                ['user_id' => $gl->id, 'service_area_id' => $swEng->id],
                ['location' => 'Building 1', 'is_active' => true],
            );
        }

        if ($data) {
            GroupLeaderAssignment::firstOrCreate(
                ['user_id' => $gl->id, 'service_area_id' => $data->id],
                ['location' => 'Building 2', 'is_active' => true],
            );
        }
    }
}
