<?php

namespace App\Livewire\Settlement;

use App\Application\Services\SettlementService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Commission Report')]
class CommissionReport extends Component
{
    #[Url] public string $dateFrom = '';
    #[Url] public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->dateTo = $this->dateTo ?: now()->toDateString();
    }

    public function render(SettlementService $settlements)
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);
        if (!$user->isAdmin() && $user->role !== 'group-leader') abort(403);

        // Reads centralised in the application service for tenant scoping.
        $commissions = $settlements->listCommissionsForUser($user, $this->dateFrom ?: null, $this->dateTo ?: null);
        $totals = [
            'revenue'    => $commissions->sum('attributed_revenue'),
            'commission' => $commissions->sum('commission_amount'),
            'orders'     => $commissions->sum('order_count'),
        ];
        $attributedOrders = $settlements->listAttributedOrdersForLeader($user, $this->dateFrom ?: null, $this->dateTo ?: null);

        return view('livewire.settlement.commission-report', [
            'commissions' => $commissions,
            'totals' => $totals,
            'attributedOrders' => $attributedOrders,
        ]);
    }
}
