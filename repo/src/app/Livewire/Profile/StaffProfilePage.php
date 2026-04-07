<?php

namespace App\Livewire\Profile;

use App\Application\Services\StaffProfileService;
use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Staff Profile')]
class StaffProfilePage extends Component
{
    use UsesApiClient;
    public string $employee_id = '';
    public string $department = '';
    public string $title = '';
    public bool $saved = false;

    public function mount(StaffProfileService $profiles): void
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);
        $profile = $profiles->findForUser($user);
        if ($profile) {
            $this->employee_id = $profile->employee_id ?? '';
            $this->department = $profile->department ?? '';
            $this->title = $profile->title ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'employee_id' => 'required|string|max:50',
            'department'  => 'required|string|max:150',
            'title'       => 'required|string|max:150',
        ]);

        $resp = $this->api()->put('/profile', [
            'employee_id' => $this->employee_id,
            'department'  => $this->department,
            'title'       => $this->title,
        ]);

        $this->saved = $resp->successful();
    }

    public function render(StaffProfileService $profiles)
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);
        $profile = $profiles->findForUser($user);
        return view('livewire.profile.staff-profile-page', ['profile' => $profile, 'user' => $user]);
    }
}
