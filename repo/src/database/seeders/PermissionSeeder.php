<?php

namespace Database\Seeders;

use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Define all permissions ──────────────────────────────────
        $permissions = [
            // Service Areas
            ['slug' => 'service-areas.create', 'description' => 'Create service areas',     'group' => 'service-areas'],
            ['slug' => 'service-areas.update', 'description' => 'Update service areas',     'group' => 'service-areas'],

            // Roles (system roles management)
            ['slug' => 'roles.create', 'description' => 'Create roles',     'group' => 'roles'],
            ['slug' => 'roles.update', 'description' => 'Update roles',     'group' => 'roles'],

            // Resources
            ['slug' => 'resources.create',     'description' => 'Create resources',          'group' => 'resources'],
            ['slug' => 'resources.update',     'description' => 'Update resources',          'group' => 'resources'],
            ['slug' => 'resources.transition', 'description' => 'Transition resource status', 'group' => 'resources'],

            // Pricing Baselines
            ['slug' => 'pricing-baselines.create', 'description' => 'Create pricing baselines', 'group' => 'pricing-baselines'],
            ['slug' => 'pricing-baselines.update', 'description' => 'Update pricing baselines', 'group' => 'pricing-baselines'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // ── Assign permissions to roles ─────────────────────────────
        // admin gets everything implicitly — no rows needed.

        $rolePermissions = [
            // group-leader: all write operations
            'group-leader' => [
                'service-areas.create', 'service-areas.update',
                'roles.create', 'roles.update',
                'resources.create', 'resources.update', 'resources.transition',
                'pricing-baselines.create', 'pricing-baselines.update',
            ],
            // staff: can create/update resources and pricing, transition status
            'staff' => [
                'resources.create', 'resources.update', 'resources.transition',
                'pricing-baselines.create', 'pricing-baselines.update',
            ],
            // user: read-only, no write permissions
        ];

        foreach ($rolePermissions as $role => $slugs) {
            foreach ($slugs as $slug) {
                $perm = Permission::where('slug', $slug)->first();
                if ($perm) {
                    RolePermission::firstOrCreate([
                        'role'          => $role,
                        'permission_id' => $perm->id,
                    ]);
                }
            }
        }
    }
}
