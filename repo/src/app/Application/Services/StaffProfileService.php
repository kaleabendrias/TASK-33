<?php

namespace App\Application\Services;

use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;

/**
 * Read-side helper for staff profiles. Write paths still go through the API.
 */
class StaffProfileService
{
    public function findForUser(User $user): ?StaffProfile
    {
        return StaffProfile::where('user_id', $user->id)->first();
    }
}
