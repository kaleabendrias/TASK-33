<?php

namespace App\Livewire\Booking;

use App\Application\Services\BookingService;
use App\Livewire\Concerns\UsesApiClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Booking')]
class BookingCreate extends Component
{
    use UsesApiClient;
    public int $step = 1;
    public array $lineItems = [];
    public string $couponCode = '';
    public string $notes = '';
    public ?int $selectedItemId = null;
    public string $bookingDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public int $quantity = 1;

    public array $totals = ['lines' => [], 'subtotal' => 0, 'tax_amount' => 0, 'discount' => 0, 'total' => 0, 'coupon_id' => null];
    public string $availabilityMsg = '';
    public string $couponMsg = '';
    public bool $couponValid = false;
    public string $error = '';

    /**
     * Snapshot of bookable item names keyed by id, captured when the line
     * item is added to the cart. The Blade view reads from THIS map instead
     * of issuing a fresh `BookableItem::find()` per row, eliminating the
     * O(N) query explosion that used to happen on every wire:model update.
     */
    public array $itemNames = [];

    public function mount(): void
    {
        $this->bookingDate = date('Y-m-d');
        $preselect = request()->query('item');
        if ($preselect) $this->selectedItemId = (int) $preselect;
    }

    public function checkAvailability(): void
    {
        $this->availabilityMsg = '';
        if (!$this->selectedItemId || !$this->bookingDate) return;

        $resp = $this->api()->post('/bookings/check-availability', [
            'bookable_item_id' => $this->selectedItemId,
            'booking_date' => $this->bookingDate,
            'start_time' => $this->startTime ?: null,
            'end_time' => $this->endTime ?: null,
            'quantity' => $this->quantity,
        ]);

        if ($resp->failed()) { $this->availabilityMsg = 'Error checking availability.'; return; }
        $data = $resp->json();
        $this->availabilityMsg = ($data['available'] ?? false) ? 'Available' : 'Unavailable: ' . implode(' ', $data['conflicts'] ?? []);
    }

    public function addLineItem(BookingService $booking): void
    {
        if (!$this->selectedItemId || !$this->bookingDate) return;

        // Capture the item name ONCE at add-to-cart time so the view never
        // has to round-trip to the database. listActiveItems() is paginated
        // to a generous page size — for the catalog sizes this product
        // targets, the cache is effectively the entire menu.
        if (!isset($this->itemNames[$this->selectedItemId])) {
            $cached = $booking->listActiveItems(perPage: 200)->getCollection();
            foreach ($cached as $item) {
                $this->itemNames[$item->id] = $item->name;
            }
        }

        $this->lineItems[] = [
            'bookable_item_id' => $this->selectedItemId,
            'booking_date' => $this->bookingDate,
            'start_time' => $this->startTime ?: null,
            'end_time' => $this->endTime ?: null,
            'quantity' => $this->quantity,
        ];
        $this->selectedItemId = null;
        $this->startTime = '';
        $this->endTime = '';
        $this->quantity = 1;
        $this->availabilityMsg = '';
        $this->recalculate();
    }

    public function removeLineItem(int $index): void
    {
        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
        $this->recalculate();
    }

    public function applyCoupon(): void
    {
        $this->couponMsg = '';
        $this->couponValid = false;
        if (!$this->couponCode || $this->totals['subtotal'] <= 0) return;

        $resp = $this->api()->post('/bookings/validate-coupon', [
            'code' => $this->couponCode,
            'subtotal' => $this->totals['subtotal'],
        ]);
        if ($resp->failed()) { $this->couponMsg = 'Error validating coupon.'; return; }
        $data = $resp->json();
        if ($data['valid'] ?? false) {
            $this->couponValid = true;
            $this->couponMsg = "Coupon applied: -\${$data['discount']}";
            $this->recalculate();
        } else {
            $this->couponMsg = $data['error'] ?? 'Invalid coupon.';
        }
    }

    public function recalculate(): void
    {
        if (empty($this->lineItems)) {
            $this->totals = ['lines' => [], 'subtotal' => 0, 'tax_amount' => 0, 'discount' => 0, 'total' => 0, 'coupon_id' => null];
            return;
        }
        $resp = $this->api()->post('/bookings/calculate-totals', [
            'line_items' => $this->lineItems,
            'coupon_code' => $this->couponValid ? $this->couponCode : null,
        ]);
        if ($resp->successful()) { $this->totals = $resp->json(); }
    }

    public function nextStep(): void
    {
        if ($this->step === 1 && empty($this->lineItems)) { $this->error = 'Add at least one item.'; return; }
        $this->error = '';
        $this->step = min($this->step + 1, 3);
    }

    public function prevStep(): void { $this->step = max($this->step - 1, 1); }

    public function submitOrder(): void
    {
        $resp = $this->api()->post('/orders', [
            'line_items' => $this->lineItems,
            'coupon_code' => $this->couponValid ? $this->couponCode : null,
            'notes' => $this->notes ?: null,
        ]);

        if ($resp->failed()) {
            $this->error = $resp->json('message') ?? 'Failed to create order.';
            return;
        }
        $order = $resp->json('data');
        session()->flash('flash_success', "Order {$order['order_number']} created.");
        $this->redirect('/orders/' . $order['id']);
    }

    public function render(BookingService $booking)
    {
        $items = $booking->listActiveItems(perPage: 100)->getCollection();
        return view('livewire.booking.booking-create', ['items' => $items]);
    }
}
