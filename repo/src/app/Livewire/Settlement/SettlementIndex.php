<?php

namespace App\Livewire\Settlement;

use App\Livewire\Concerns\UsesApiClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Settlements')]
class SettlementIndex extends Component
{
    use WithPagination, UsesApiClient;

    public string $periodStart = '';
    public string $periodEnd = '';
    public string $cycleType = 'weekly';
    public string $message = '';

    public function mount(): void
    {
        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->toDateString();

        // Authorize at mount so abort(403) propagates as a hard 403
        // through Livewire's render harness. Doing the gate inside
        // render() lets Livewire wrap the HTTP exception in a soft
        // 200, which masks the denial in test assertions.
        $resp = $this->api()->get('/settlements');
        if ($resp->status() === 401) abort(401);
        if ($resp->status() === 403) abort(403);
    }

    public function generate(): void
    {
        $resp = $this->api()->post('/admin/settlements/generate', [
            'period_start' => $this->periodStart,
            'period_end'   => $this->periodEnd,
            'cycle_type'   => $this->cycleType,
        ]);

        if ($resp->failed()) { $this->message = $resp->json('message') ?? 'Generation failed.'; return; }
        $data = $resp->json('data');
        $disc = $resp->json('discrepancies', []);
        $this->message = empty($disc)
            ? "Settlement {$data['reference']} generated and reconciled. Net: \${$data['net_amount']}"
            : "Settlement generated with " . count($disc) . " discrepancy(ies).";
    }

    public function finalize(int $id): void
    {
        $resp = $this->api()->post("/admin/settlements/{$id}/finalize");
        if ($resp->failed()) { $this->message = $resp->json('message') ?? 'Finalize failed.'; return; }
        $data = $resp->json('data');
        $this->message = "Settlement {$data['reference']} finalized.";
    }

    public function render()
    {
        // Read through the API so authorization, role gating, and
        // row-level scoping are enforced uniformly with REST clients.
        // The /settlements endpoint is gated to role:staff (staff,
        // group-leader, and admin) by the API middleware and applies
        // row-level scoping inside SettlementService — Livewire never
        // touches the service or models directly.
        $resp = $this->api()->get('/settlements');
        if ($resp->status() === 401) abort(401);
        if ($resp->status() === 403) abort(403);
        $payload = $resp->successful() ? ($resp->json() ?? []) : [];

        // Re-hydrate as a paginator so the existing Blade view
        // (which iterates with `->items()`) keeps working without
        // changes.
        $settlements = new LengthAwarePaginator(
            items: $payload['data'] ?? [],
            total: $payload['total'] ?? 0,
            perPage: $payload['per_page'] ?? 10,
            currentPage: $payload['current_page'] ?? 1,
            options: ['path' => request()->url()],
        );

        return view('livewire.settlement.settlement-index', [
            'settlements' => $settlements,
            // Effective permissions are passed in so the blade can
            // gate buttons by the SAME slug the API enforces.
            'effectivePermissions' => $this->effectivePermissions(),
        ]);
    }
}
