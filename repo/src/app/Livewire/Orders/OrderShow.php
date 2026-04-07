<?php

namespace App\Livewire\Orders;

use App\Application\Services\OrderQueryService;
use App\Domain\Models\Order;
use App\Domain\Policies\OrderPolicy;
use App\Livewire\Concerns\UsesApiClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
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
        // Authorize on first load — abort with 403 if not allowed to view.
        $order = Order::findOrFail($orderId);
        if (!Gate::allows('view', $order)) {
            abort(403, 'You are not authorized to view this order.');
        }
    }

    public function checkIn(): void { $this->guardOperational('checked_in') && $this->callTransition('checked_in'); }
    public function checkOut(): void { $this->guardOperational('checked_out') && $this->callTransition('checked_out'); }
    public function complete(): void { $this->guardOperational('completed') && $this->callTransition('completed'); }

    public function cancel(): void
    {
        // Self-service: gate authorize against the policy before calling the API.
        $order = Order::findOrFail($this->orderId);
        if (!Gate::allows('transition', [$order, 'cancelled'])) {
            $this->error = 'You are not authorized to cancel this order.';
            return;
        }
        $this->callTransition('cancelled', $this->cancelReason ?: null);
    }

    public function refund(): void
    {
        $order = Order::findOrFail($this->orderId);
        if (!Gate::allows('refund', $order)) {
            $this->error = 'You are not authorized to refund this order.';
            return;
        }
        $resp = $this->api()->post("/orders/{$this->orderId}/refund", ['reason' => 'Customer requested']);
        if ($resp->failed()) { $this->error = $resp->json('message') ?? 'Refund failed.'; }
    }

    public function markUnavailable(): void
    {
        $order = Order::findOrFail($this->orderId);
        if (!Gate::allows('markUnavailable', $order)) {
            $this->error = 'You are not authorized.';
            return;
        }
        $resp = $this->api()->post("/orders/{$this->orderId}/mark-unavailable");
        if ($resp->failed()) { $this->error = $resp->json('message') ?? 'Failed.'; }
    }

    /** Defense-in-depth: only staff+ may invoke operational transitions in the UI. */
    private function guardOperational(string $newStatus): bool
    {
        $order = Order::findOrFail($this->orderId);
        if (!Gate::allows('transition', [$order, $newStatus])) {
            $this->error = 'You are not authorized to perform this operational action.';
            return false;
        }
        return true;
    }

    private function callTransition(string $status, ?string $reason = null): void
    {
        $resp = $this->api()->post("/orders/{$this->orderId}/transition", [
            'status' => $status,
            'reason' => $reason,
        ]);
        if ($resp->failed()) { $this->error = $resp->json('message') ?? 'Transition failed.'; }
    }

    public function render(OrderQueryService $orders)
    {
        $order = $orders->findWithDetail($this->orderId);
        // Re-authorize on every render — context can change.
        if (!Gate::allows('view', $order)) {
            abort(403);
        }
        return view('livewire.orders.order-show', ['order' => $order]);
    }
}
