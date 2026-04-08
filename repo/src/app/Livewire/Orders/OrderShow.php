<?php

namespace App\Livewire\Orders;

use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Order Detail')]
class OrderShow extends Component
{
    use UsesApiClient;

    public int $orderId;
    public string $cancelReason = '';
    public string $error = '';

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
        // Authorize on first load by going through the API. The
        // OrderApiController already runs Gate::authorize('view', ...),
        // so we get authorization parity with REST consumers without
        // touching the model directly here.
        $resp = $this->api()->get("/orders/{$orderId}");
        if ($resp->status() === 403) {
            abort(403, 'You are not authorized to view this order.');
        }
        if ($resp->status() === 404) {
            abort(404);
        }
        if ($resp->failed()) {
            abort(500, 'Failed to load order.');
        }
    }

    public function checkIn(): void { $this->callTransition('checked_in'); }
    public function checkOut(): void { $this->callTransition('checked_out'); }
    public function complete(): void { $this->callTransition('completed'); }

    public function cancel(): void
    {
        // Authorization is enforced by the API endpoint via Gate::authorize.
        $this->callTransition('cancelled', $this->cancelReason ?: null);
    }

    public function refund(): void
    {
        $resp = $this->api()->post("/orders/{$this->orderId}/refund", ['reason' => 'Customer requested']);
        if ($resp->status() === 403) {
            $this->error = 'You are not authorized to refund this order.';
            return;
        }
        if ($resp->failed()) { $this->error = $resp->json('message') ?? 'Refund failed.'; }
    }

    public function markUnavailable(): void
    {
        $resp = $this->api()->post("/orders/{$this->orderId}/mark-unavailable");
        if ($resp->status() === 403) {
            $this->error = 'You are not authorized.';
            return;
        }
        if ($resp->failed()) { $this->error = $resp->json('message') ?? 'Failed.'; }
    }

    private function callTransition(string $status, ?string $reason = null): void
    {
        $resp = $this->api()->post("/orders/{$this->orderId}/transition", [
            'status' => $status,
            'reason' => $reason,
        ]);
        if ($resp->status() === 403) {
            $this->error = 'You are not authorized to perform this operational action.';
            return;
        }
        if ($resp->failed()) { $this->error = $resp->json('message') ?? 'Transition failed.'; }
    }

    public function render()
    {
        // Read through the API so authorization is enforced uniformly
        // with REST consumers — no direct service or model access.
        $resp = $this->api()->get("/orders/{$this->orderId}");
        if ($resp->status() === 403) {
            abort(403);
        }
        if ($resp->failed()) {
            abort($resp->status() ?: 500);
        }
        $order = $resp->json('data');
        return view('livewire.orders.order-show', ['order' => $order]);
    }
}
