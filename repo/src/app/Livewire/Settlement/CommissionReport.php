<?php

namespace App\Livewire\Settlement;

use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Commission Report')]
class CommissionReport extends Component
{
    use UsesApiClient;

    #[Url] public string $dateFrom = '';
    #[Url] public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->dateTo = $this->dateTo ?: now()->toDateString();
    }

    /**
     * Reads are now strictly API-mediated. The component never injects
     * SettlementService — instead it goes through the same endpoints
     * REST consumers use, so role gating, scope, and totals stay aligned
     * across the surface area.
     */
    public function render()
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);
        if (!$user->isAdmin() && $user->role !== 'group-leader') abort(403);

        $params = [
            'from' => $this->dateFrom ?: null,
            'to'   => $this->dateTo ?: null,
        ];

        $commissionsResp = $this->api()->get('/commissions', $params);
        if ($commissionsResp->status() === 401) abort(401);
        if ($commissionsResp->status() === 403) abort(403);

        // Normalize the API JSON rows to stdClass so the blade template
        // and the existing isolation tests can keep dereferencing fields
        // with object syntax (`$c->group_leader_id`) regardless of
        // whether the data came from Eloquent or a JSON wire payload.
        $commissions = collect($commissionsResp->json('data') ?? [])
            ->map(fn ($row) => is_array($row) ? (object) $row : $row);
        $totals = $commissionsResp->json('totals') ?? [
            'revenue' => 0, 'commission' => 0, 'orders' => 0,
        ];

        $ordersResp = $this->api()->get('/commissions/attributed-orders', $params);
        $attributedOrders = collect($ordersResp->successful() ? ($ordersResp->json('data') ?? []) : [])
            ->map(fn ($row) => is_array($row) ? (object) $row : $row);

        return view('livewire.settlement.commission-report', [
            'commissions' => $commissions,
            'totals' => $totals,
            'attributedOrders' => $attributedOrders,
        ]);
    }
}
