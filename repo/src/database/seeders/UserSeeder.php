<?php

namespace Database\Seeders;

use App\Domain\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'username'  => 'admin',
                'password'  => 'Admin@12345678',
                'full_name' => 'System Administrator',
                'role'      => 'admin',
            ],
            [
                'username'  => 'groupleader',
                'password'  => 'Leader@1234567',
                'full_name' => 'Group Leader Demo',
                'role'      => 'group-leader',
            ],
            [
                'username'  => 'staff',
                'password'  => 'Staff@12345678',
                'full_name' => 'Staff Demo User',
                'role'      => 'staff',
            ],
            [
                'username'  => 'viewer',
                'password'  => 'Viewer@1234567',
                'full_name' => 'Viewer Demo User',
                'role'      => 'user',
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(
                ['username' => $data['username']],
                array_merge($data, ['must_change_password' => true]),
            );
        }
    }
}
