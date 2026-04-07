<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Orders</h1>

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by order #…" class="flex-1 rounded-lg border-surface-200 text-sm" aria-label="Search orders"/>
        <select wire:model.live="statusFilter" class="rounded-lg border-surface-200 text-sm w-full sm:w-48" aria-label="Filter by status">
            <option value="">All Statuses</option>
            @foreach(['draft','confirmed','checked_in','checked_out','completed','cancelled','refunded'] as $s)
                <option value="{{ $s }}">{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-x-auto">
        <table class="w-full text-sm" role="table">
            <thead>
                <tr class="border-b border-surface-200 text-left text-surface-500 text-xs uppercase">
                    <th class="px-4 py-3">Order #</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3">User</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $orderRaw)
                @php
                    // OrderIndex now consumes the API JSON payload directly,
                    // so each $order is an associative array.
                    $order = (object) (is_array($orderRaw) ? $orderRaw : $orderRaw->toArray());
                    $colors = ['confirmed'=>'blue','checked_in'=>'indigo','checked_out'=>'violet','completed'=>'green','cancelled'=>'red','refunded'=>'amber','draft'=>'gray','pending'=>'yellow'];
                    $createdAt = !empty($order->created_at) ? \Carbon\Carbon::parse($order->created_at)->format('M d, Y') : '';
                @endphp
                <tr class="border-b border-surface-100 hover:bg-surface-50">
                    <td class="px-4 py-3 font-medium">{{ $order->order_number ?? '' }}</td>
                    <td class="px-4 py-3">{{ $createdAt }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $colors[$order->status ?? ''] ?? 'gray' }}-100 text-{{ $colors[$order->status ?? ''] ?? 'gray' }}-700">{{ ucfirst(str_replace('_',' ',$order->status ?? '')) }}</span>
                    </td>
                    <td class="px-4 py-3 text-right font-medium">${{ number_format((float) ($order->total ?? 0), 2) }}</td>
                    <td class="px-4 py-3 text-surface-500">{{ $order->user_id ?? '—' }}</td>
                    <td class="px-4 py-3"><a href="/orders/{{ $order->id ?? '' }}" class="text-brand-600 hover:underline text-sm" tabindex="0">View</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-surface-500">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</div>
