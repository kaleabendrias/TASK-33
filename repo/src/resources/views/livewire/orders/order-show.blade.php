<div>
    {{-- $order is the JSON payload returned by GET /api/orders/{id}; the
         component routes everything through the API layer so authorization
         parity is preserved. All field access is array-based, snake_case. --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="/orders" class="text-surface-500 hover:text-surface-700 text-sm" tabindex="0">← Orders</a>
        <h1 class="text-2xl font-bold text-surface-900">{{ $order['order_number'] ?? '' }}</h1>
        @php $colors = ['confirmed'=>'blue','checked_in'=>'indigo','checked_out'=>'violet','completed'=>'green','cancelled'=>'red','refunded'=>'amber','draft'=>'gray']; @endphp
        @php $status = $order['status'] ?? 'draft'; @endphp
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-{{ $colors[$status] ?? 'gray' }}-100 text-{{ $colors[$status] ?? 'gray' }}-700">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
    </div>

    @if($error)<div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-red-700 text-sm" role="alert">{{ $error }}</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Line items --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <h2 class="text-lg font-semibold mb-4">Line Items</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" role="table" aria-label="Order line items">
                    <thead><tr class="border-b text-left text-surface-500 text-xs uppercase"><th class="py-2 pr-3">Item</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Time</th><th class="py-2 pr-3 text-right">Qty</th><th class="py-2 pr-3 text-right">Subtotal</th><th class="py-2 text-right">Tax</th><th class="py-2 text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach(($order['line_items'] ?? []) as $li)
                        <tr class="border-b border-surface-100">
                            <td class="py-2 pr-3 font-medium">{{ $li['item_name'] ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ !empty($li['booking_date']) ? \Illuminate\Support\Carbon::parse($li['booking_date'])->format('M d') : '' }}</td>
                            <td class="py-2 pr-3">{{ $li['start_time'] ?? '' }} {{ !empty($li['end_time']) ? '– '.$li['end_time'] : '' }}</td>
                            <td class="py-2 pr-3 text-right">{{ $li['quantity'] ?? 0 }}</td>
                            <td class="py-2 pr-3 text-right">${{ number_format((float)($li['line_subtotal'] ?? 0), 2) }}</td>
                            <td class="py-2 text-right">${{ number_format((float)($li['line_tax'] ?? 0), 2) }}</td>
                            <td class="py-2 text-right font-medium">${{ number_format((float)($li['line_total'] ?? 0), 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Summary & Actions --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
                <h2 class="text-lg font-semibold mb-3">Summary</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>${{ number_format((float)($order['subtotal'] ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Tax</dt><dd>${{ number_format((float)($order['tax_amount'] ?? 0), 2) }}</dd></div>
                    @if(($order['discount_amount'] ?? 0) > 0)<div class="flex justify-between text-green-600"><dt>Discount</dt><dd>-${{ number_format((float)$order['discount_amount'], 2) }}</dd></div>@endif
                    <div class="flex justify-between font-bold text-lg border-t pt-2"><dt>Total</dt><dd>${{ number_format((float)($order['total'] ?? 0), 2) }}</dd></div>
                </dl>
                @if(!empty($order['coupon_id']))<p class="text-xs text-surface-500 mt-2">Coupon applied (#{{ $order['coupon_id'] }})</p>@endif
                @if(!empty($order['group_leader_id']))<p class="text-xs text-surface-500 mt-1">Group Leader: #{{ $order['group_leader_id'] }}</p>@endif
                @if(!empty($order['notes']))<p class="text-xs text-surface-400 mt-2 italic">{{ $order['notes'] }}</p>@endif
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 space-y-2">
                <h2 class="text-lg font-semibold mb-3">Actions</h2>
                @if($status === 'confirmed')
                    <button wire:click="checkIn" class="w-full py-2 px-4 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition" tabindex="0">Check In</button>
                    <button wire:click="cancel" class="w-full py-2 px-4 rounded-lg bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100 transition" tabindex="0">Cancel</button>
                @elseif($status === 'checked_in')
                    <button wire:click="checkOut" class="w-full py-2 px-4 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition" tabindex="0">Check Out</button>
                @elseif($status === 'checked_out')
                    <button wire:click="complete" class="w-full py-2 px-4 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition" tabindex="0">Complete</button>
                @endif
                @if(in_array($status, ['completed','cancelled']))
                    <button wire:click="refund" class="w-full py-2 px-4 rounded-lg bg-amber-50 text-amber-700 text-sm font-medium hover:bg-amber-100 transition" tabindex="0">
                        Process Refund
                    </button>
                    @if(empty($order['staff_marked_unavailable']))
                        <button wire:click="markUnavailable" class="w-full py-2 px-4 rounded-lg bg-surface-100 text-surface-600 text-sm hover:bg-surface-200 transition" tabindex="0">Mark Staff Unavailable (waive fee)</button>
                    @endif
                @endif
            </div>

            {{-- Refund history --}}
            @if(!empty($order['refunds']))
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
                <h3 class="text-sm font-semibold mb-2">Refunds</h3>
                @foreach($order['refunds'] as $r)
                    <div class="text-xs text-surface-600 py-1 border-b border-surface-100 last:border-0">
                        ${{ number_format((float)($r['refund_amount'] ?? 0), 2) }} {{ ($r['is_full_refund'] ?? false) ? '(full)' : "(fee: \${$r['cancellation_fee']})" }} — {{ !empty($r['processed_at']) ? \Illuminate\Support\Carbon::parse($r['processed_at'])->format('M d H:i') : '' }}
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
