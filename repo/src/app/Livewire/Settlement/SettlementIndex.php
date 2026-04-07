<?php

namespace App\Livewire\Settlement;

use App\Application\Services\SettlementService;
use App\Livewire\Concerns\UsesApiClient;
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

    public function render(SettlementService $settlements)
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);
        // Settlement summaries are now visible to staff, group-leader, and admin.
        // Row-level scoping happens inside SettlementService::listSettlementsForUser.
        if (!in_array($user->role, ['staff', 'group-leader', 'admin'], true)) {
            abort(403);
        }

        return view('livewire.settlement.settlement-index', [
            'settlements' => $settlements->listSettlementsForUser($user),
        ]);
    }
}
