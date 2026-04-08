<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class DashboardPage extends Component
{
    use UsesApiClient;

    /**
     * Date-range window. Defaults to the current calendar month so the
     * historic "Month Revenue" / "My Orders this month" cards keep
     * displaying the same numbers when the page first loads — but the
     * user can now widen or narrow the window via the date inputs and
     * the cards will requery the API live.
     */
    #[Url] public string $dateFrom = '';
    #[Url] public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->dateTo   = $this->dateTo ?: now()->toDateString();
    }

    public function render()
    {
        // Read through the API so authorization and aggregation rules
        // stay synchronized with REST consumers — the component never
        // injects DashboardService or hits Eloquent directly.
        $resp = $this->api()->get('/dashboard/stats', [
            'from' => $this->dateFrom ?: null,
            'to'   => $this->dateTo ?: null,
        ]);
        if ($resp->status() === 401) abort(401);
        $stats = $resp->successful() ? ($resp->json('data') ?? []) : [];

        return view('livewire.dashboard.dashboard-page', $stats);
    }
}
