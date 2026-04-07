<?php

namespace App\Livewire\Booking;

use App\Livewire\Concerns\UsesApiClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Bookings')]
class BookingIndex extends Component
{
    use WithPagination, UsesApiClient;

    #[Url]
    public string $search = '';
    #[Url]
    public string $typeFilter = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }

    /**
     * Fetches the bookable item catalog through the public REST API.
     * This satisfies the API-decoupled architecture requirement: the
     * Livewire component performs no direct service or model calls.
     */
    public function render()
    {
        $resp = $this->api()->get('/bookings/items', [
            'search' => $this->search ?: null,
            'type'   => $this->typeFilter ?: null,
            'page'   => $this->getPage(),
            'per_page' => 12,
        ]);

        $payload = $resp->successful() ? $resp->json() : ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];

        // Re-hydrate as a paginator so the existing Blade view (which expects
        // ->links() and iterable items) keeps working without changes.
        $items = new LengthAwarePaginator(
            items: $payload['data'] ?? [],
            total: $payload['total'] ?? 0,
            perPage: $payload['per_page'] ?? 12,
            currentPage: $payload['current_page'] ?? 1,
            options: ['path' => request()->url()],
        );

        return view('livewire.booking.booking-index', ['items' => $items]);
    }
}
