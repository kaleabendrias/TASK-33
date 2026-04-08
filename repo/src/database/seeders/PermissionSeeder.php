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

            // Settlements (button-level: admin can generate/finalize)
            ['slug' => 'settlements.generate', 'description' => 'Generate settlements', 'group' => 'settlements'],
            ['slug' => 'settlements.finalize', 'description' => 'Finalize settlements', 'group' => 'settlements'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // ── Assign permissions to roles ─────────────────────────────
        // admin gets everything implicitly — no rows needed.
        //
        // Per the original requirements, foundational entities (service
        // areas, system roles, resources, pricing baselines) are
        // ADMIN-ONLY for all write operations. Staff and group-leaders
        // must not be able to mutate billing or operational baselines;
        // their write surface is limited to operational order/booking
        // flows handled elsewhere.
        $rolePermissions = [
            // group-leader: read-only on foundational entities
            'group-leader' => [],
            // staff: read-only on foundational entities
            'staff' => [],
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
