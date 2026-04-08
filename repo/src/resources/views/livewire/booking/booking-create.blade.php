<div>
    {{-- Profile completion is now enforced by the profile.complete middleware on staff+ write routes.
         Regular users can book without a staff profile. --}}

    <h1 class="text-2xl font-bold text-surface-900 mb-6">Create Booking</h1>

    {{-- Step indicator --}}
    <nav class="flex items-center gap-2 mb-8" aria-label="Booking steps">
        @foreach(['Select Items', 'Review & Coupon', 'Confirm'] as $i => $label)
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $step > $i+1 ? 'bg-green-500 text-white' : ($step === $i+1 ? 'bg-brand-600 text-white' : 'bg-surface-200 text-surface-500') }}">{{ $i+1 }}</span>
                <span class="text-sm {{ $step === $i+1 ? 'font-semibold text-surface-900' : 'text-surface-500' }} hidden sm:inline">{{ $label }}</span>
                @if($i < 2)<span class="text-surface-300 mx-1">→</span>@endif
            </div>
        @endforeach
    </nav>

    @if($error)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-red-700 text-sm" role="alert">{{ $error }}</div>
    @endif

    {{-- STEP 1: Select Items --}}
    @if($step === 1)
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold text-surface-800 mb-4">Add Items</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div>
                <label for="item-select" class="block text-sm font-medium text-surface-700 mb-1">Item</label>
                <select wire:model.live="selectedItemId" id="item-select" class="w-full rounded-lg border-surface-200 text-sm" aria-label="Select bookable item">
                    <option value="">Choose…</option>
                    {{-- $items is the JSON array returned by GET /api/bookings/items --}}
                    @foreach($items as $item)
                        <option value="{{ $item['id'] ?? '' }}">{{ $item['name'] ?? '' }} ({{ ucfirst($item['type'] ?? '') }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="booking-date" class="block text-sm font-medium text-surface-700 mb-1">Date</label>
                <input wire:model.live="bookingDate" id="booking-date" type="date" class="w-full rounded-lg border-surface-200 text-sm"/>
            </div>
            <div>
                <label for="start-time" class="block text-sm font-medium text-surface-700 mb-1">Start Time</label>
                <input wire:model="startTime" id="start-time" type="time" class="w-full rounded-lg border-surface-200 text-sm"/>
            </div>
            <div>
                <label for="end-time" class="block text-sm font-medium text-surface-700 mb-1">End Time</label>
                <input wire:model="endTime" id="end-time" type="time" class="w-full rounded-lg border-surface-200 text-sm"/>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="w-24">
                <label for="qty" class="block text-sm font-medium text-surface-700 mb-1">Qty</label>
                <input wire:model="quantity" id="qty" type="number" min="1" class="w-full rounded-lg border-surface-200 text-sm"/>
            </div>
            <button wire:click="checkAvailability" class="mt-5 px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm hover:bg-surface-200 transition" tabindex="0">Check Availability</button>
            <button wire:click="addLineItem" class="mt-5 px-4 py-2 rounded-lg bg-brand-600 text-white text-sm hover:bg-brand-700 transition" tabindex="0">
                <x-icon name="plus" class="w-4 h-4 inline -mt-0.5"/> Add
            </button>
            @if($availabilityMsg)
                <span class="mt-5 text-sm {{ str_starts_with($availabilityMsg, '✓') ? 'text-green-600' : 'text-red-600' }}" role="status">{{ $availabilityMsg }}</span>
            @endif
        </div>

        {{-- Line items table --}}
        @if(count($lineItems))
        <div class="overflow-x-auto" role="table" aria-label="Selected items">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-surface-200 text-left text-surface-500">
                        <th class="py-2 pr-4">Item</th><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Time</th><th class="py-2 pr-4 text-right">Qty</th><th class="py-2 pr-4 text-right">Price</th><th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($lineItems as $idx => $li)
                    {{-- Item name pulled from the component-state cache populated
                         by addLineItem() — no per-row Eloquent query. --}}
                    <tr class="border-b border-surface-100">
                        <td class="py-2 pr-4 font-medium">{{ $itemNames[$li['bookable_item_id']] ?? 'Unknown' }}</td>
                        <td class="py-2 pr-4">{{ $li['booking_date'] }}</td>
                        <td class="py-2 pr-4">{{ $li['start_time'] ?? '' }} {{ $li['end_time'] ? '– '.$li['end_time'] : '' }}</td>
                        <td class="py-2 pr-4 text-right">{{ $li['quantity'] }}</td>
                        <td class="py-2 pr-4 text-right">${{ number_format($totals['lines'][$idx]['line_total'] ?? 0, 2) }}</td>
                        <td class="py-2"><button wire:click="removeLineItem({{ $idx }})" class="text-red-500 hover:text-red-700 text-xs" aria-label="Remove item" tabindex="0">✕</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- STEP 2: Review & Coupon --}}
    @elseif($step === 2)
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold text-surface-800 mb-4">Review & Coupon</h2>

        <div class="border-b border-surface-200 pb-4 mb-4">
            @foreach($totals['lines'] as $line)
                <div class="flex justify-between text-sm py-1">
                    <span>{{ $line['item_name'] }} × {{ $line['quantity'] }} ({{ $line['booking_date'] }})</span>
                    <span>${{ number_format($line['line_total'], 2) }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex items-end gap-3 mb-4">
            <div class="flex-1">
                <label for="coupon" class="block text-sm font-medium text-surface-700 mb-1">Coupon Code</label>
                <input wire:model="couponCode" id="coupon" type="text" placeholder="Enter code" class="w-full rounded-lg border-surface-200 text-sm"/>
            </div>
            <button wire:click="applyCoupon" class="px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm hover:bg-surface-200 transition" tabindex="0">Apply</button>
        </div>
        @if($couponMsg)
            <p class="text-sm mb-4 {{ $couponValid ? 'text-green-600' : 'text-red-600' }}" role="status">{{ $couponMsg }}</p>
        @endif

        {{-- Totals --}}
        <div class="space-y-2 text-sm" aria-label="Order totals">
            <div class="flex justify-between"><span>Subtotal</span><span>${{ number_format($totals['subtotal'], 2) }}</span></div>
            <div class="flex justify-between"><span>Tax</span><span>${{ number_format($totals['tax_amount'], 2) }}</span></div>
            @if($totals['discount'] > 0)
                <div class="flex justify-between text-green-600"><span>Discount</span><span>-${{ number_format($totals['discount'], 2) }}</span></div>
            @endif
            <div class="flex justify-between font-bold text-lg border-t border-surface-200 pt-2"><span>Total</span><span>${{ number_format($totals['total'], 2) }}</span></div>
        </div>

        <div class="mt-4">
            <label for="notes" class="block text-sm font-medium text-surface-700 mb-1">Notes (optional)</label>
            <textarea wire:model="notes" id="notes" rows="2" class="w-full rounded-lg border-surface-200 text-sm"></textarea>
        </div>
    </div>

    {{-- STEP 3: Confirm --}}
    @else
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 text-center">
        <x-icon name="check" class="w-16 h-16 text-brand-500 mx-auto mb-4"/>
        <h2 class="text-xl font-bold text-surface-900 mb-2">Confirm Your Order</h2>
        <p class="text-surface-500 mb-1">{{ count($lineItems) }} item(s) — <strong>${{ number_format($totals['total'], 2) }}</strong></p>
        <p class="text-xs text-surface-400 mb-6">You will be charged immediately upon confirmation.</p>
        <button
            wire:click="submitOrder"
            wire:loading.attr="disabled"
            class="px-8 py-3 rounded-lg bg-brand-600 text-white font-semibold hover:bg-brand-700 transition disabled:opacity-50"
            tabindex="0"
        >
            <span wire:loading.remove>Place Order</span>
            <span wire:loading>Processing…</span>
        </button>
    </div>
    @endif

    {{-- Navigation --}}
    <div class="flex justify-between mt-6">
        @if($step > 1)
            <button wire:click="prevStep" class="px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm hover:bg-surface-200 transition" tabindex="0">← Back</button>
        @else
            <span></span>
        @endif
        @if($step < 3)
            <button wire:click="nextStep" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm hover:bg-brand-700 transition" tabindex="0">Next →</button>
        @endif
    </div>
</div>
