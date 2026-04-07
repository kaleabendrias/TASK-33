<?php

namespace App\Livewire\Dashboard;

use App\Application\Services\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class DashboardPage extends Component
{
    public function render(DashboardService $dashboard)
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);

        // All dashboard stats are aggregated by the application service so the
        // Livewire component never queries Eloquent directly.
        return view('livewire.dashboard.dashboard-page', $dashboard->statsFor($user));
    }
}
