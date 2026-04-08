<?php

namespace App\Livewire\Profile;

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

    public function mount(): void
    {
        // Read through the API to keep authorization parity with REST
        // clients. The endpoint enforces auth via jwt.auth middleware
        // and only ever exposes the caller's own profile.
        $resp = $this->api()->get('/profile');
        if ($resp->status() === 401) abort(401);
        if ($resp->successful()) {
            $profile = $resp->json('data') ?? [];
            $this->employee_id = $profile['employee_id'] ?? '';
            $this->department  = $profile['department'] ?? '';
            $this->title       = $profile['title'] ?? '';
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

    public function render()
    {
        // Read through the API — authorization is enforced by jwt.auth.
        $resp = $this->api()->get('/profile');
        if ($resp->status() === 401) abort(401);
        $payload = $resp->successful() ? ($resp->json() ?? []) : [];

        // The /auth/me endpoint exposes the caller's own user record;
        // route this through the API too rather than reaching into the
        // request attributes for the auth user.
        $meResp = $this->api()->get('/auth/me');
        $user = $meResp->successful() ? ($meResp->json('data') ?? []) : [];

        return view('livewire.profile.staff-profile-page', [
            'profile'    => $payload['data'] ?? null,
            'isComplete' => $payload['is_complete'] ?? false,
            'user'       => $user,
        ]);
    }
}
