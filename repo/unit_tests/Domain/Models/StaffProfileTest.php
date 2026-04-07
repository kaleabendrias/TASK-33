<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use UnitTests\TestCase;

class StaffProfileTest extends TestCase
{
    public function test_is_complete_with_all_fields(): void
    {
        $user = User::create(['username' => 'prof_full', 'password' => 'TestPass@12345!', 'full_name' => 'P', 'role' => 'staff']);
        $profile = StaffProfile::create(['user_id' => $user->id, 'employee_id' => 'EMP001', 'department' => 'Engineering', 'title' => 'Senior Dev']);
        $this->assertTrue($profile->isComplete());
    }

    public function test_is_incomplete_missing_employee_id(): void
    {
        $user = User::create(['username' => 'prof_miss', 'password' => 'TestPass@12345!', 'full_name' => 'P', 'role' => 'staff']);
        $profile = StaffProfile::create(['user_id' => $user->id, 'department' => 'Engineering', 'title' => 'Dev']);
        $this->assertFalse($profile->isComplete());
    }

    public function test_is_incomplete_missing_department(): void
    {
        $user = User::create(['username' => 'prof_dept', 'password' => 'TestPass@12345!', 'full_name' => 'P', 'role' => 'staff']);
        $profile = StaffProfile::create(['user_id' => $user->id, 'employee_id' => 'EMP002', 'title' => 'Dev']);
        $this->assertFalse($profile->isComplete());
    }

    public function test_is_incomplete_missing_title(): void
    {
        $user = User::create(['username' => 'prof_title', 'password' => 'TestPass@12345!', 'full_name' => 'P', 'role' => 'staff']);
        $profile = StaffProfile::create(['user_id' => $user->id, 'employee_id' => 'EMP003', 'department' => 'Eng']);
        $this->assertFalse($profile->isComplete());
    }
}
