<?php

namespace App\Livewire\Orders;

use App\Livewire\Concerns\UsesApiClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Orders')]
class OrderIndex extends Component
{
    use WithPagination, UsesApiClient;

    #[Url] public string $statusFilter = '';
    #[Url] public string $search = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    /**
     * Fetches paginated orders strictly through the REST API. Tenant isolation
     * is enforced server-side by OrderApiController::index using the JWT bearer
     * token, so this component never queries the database directly.
     */
    public function render()
    {
        $user = auth()->user() ?? request()->attributes->get('auth_user');
        if (!$user) abort(401);

        // The search input is now wired straight through to the API so the
        // server-side filter (case-insensitive ILIKE on order_number) does
        // the work — no client-side filtering, no double-pagination bugs.
        $resp = $this->api()->get('/orders', [
            'status'   => $this->statusFilter ?: null,
            'search'   => $this->search ?: null,
            'page'     => $this->getPage(),
            'per_page' => 15,
        ]);

        $payload = $resp->successful() ? $resp->json() : ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 15];

        $orders = new LengthAwarePaginator(
            items: $payload['data'] ?? [],
            total: $payload['total'] ?? 0,
            perPage: $payload['per_page'] ?? 15,
            currentPage: $payload['current_page'] ?? 1,
            options: ['path' => request()->url()],
        );

        return view('livewire.orders.order-index', ['orders' => $orders]);
    }
}
