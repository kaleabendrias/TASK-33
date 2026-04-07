<?php

namespace Database\Seeders;

use App\Domain\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Junior',         'slug' => 'junior',         'description' => 'Entry-level practitioner',                  'level' => 1],
            ['name' => 'Mid-Level',      'slug' => 'mid-level',      'description' => 'Intermediate practitioner',                 'level' => 2],
            ['name' => 'Senior',         'slug' => 'senior',         'description' => 'Experienced practitioner',                  'level' => 3],
            ['name' => 'Lead',           'slug' => 'lead',           'description' => 'Team lead / tech lead',                     'level' => 4],
            ['name' => 'Principal',      'slug' => 'principal',      'description' => 'Principal-level subject matter expert',     'level' => 5],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
