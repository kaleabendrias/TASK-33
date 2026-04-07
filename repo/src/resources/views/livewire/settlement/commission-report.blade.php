<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Commission Report</h1>

    {{-- Date range filter --}}
    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div><label class="block text-sm font-medium text-surface-700 mb-1">From</label><input wire:model.live="dateFrom" type="date" class="rounded-lg border-surface-200 text-sm"/></div>
        <div><label class="block text-sm font-medium text-surface-700 mb-1">To</label><input wire:model.live="dateTo" type="date" class="rounded-lg border-surface-200 text-sm"/></div>
    </div>

    {{-- Performance totals --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-card label="Attributed Revenue" :value="'$'.number_format($totals['revenue'], 2)" icon="banknotes"/>
        <x-stat-card label="Commission Earned" :value="'$'.number_format($totals['commission'], 2)" icon="chart-bar"/>
        <x-stat-card label="Total Orders" :value="$totals['orders']" icon="clipboard"/>
    </div>

    {{-- Commission cycles --}}
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-x-auto mb-6">
        <table class="w-full text-sm" role="table" aria-label="Commission cycles">
            <thead><tr class="border-b text-left text-surface-500 text-xs uppercase"><th class="px-4 py-3">Cycle</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Revenue</th><th class="px-4 py-3 text-right">Rate</th><th class="px-4 py-3 text-right">Commission</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Hold Until</th></tr></thead>
            <tbody>
                @forelse($commissions as $c)
                <tr class="border-b border-surface-100">
                    <td class="px-4 py-3">{{ $c->cycle_start->format('M d') }} – {{ $c->cycle_end->format('M d') }}</td>
                    <td class="px-4 py-3">{{ ucfirst($c->cycle_type) }}</td>
                    <td class="px-4 py-3 text-right">${{ number_format($c->attributed_revenue, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($c->commission_rate * 100, 1) }}%</td>
                    <td class="px-4 py-3 text-right font-bold">${{ number_format($c->commission_amount, 2) }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->status === 'paid' ? 'bg-green-100 text-green-700' : ($c->status === 'held' ? 'bg-amber-100 text-amber-700' : 'bg-surface-100 text-surface-700') }}">{{ ucfirst($c->status) }}</span></td>
                    <td class="px-4 py-3 text-xs text-surface-500">{{ $c->hold_until?->format('M d, Y') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-surface-500">No commissions for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Attributed orders --}}
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold mb-4">Attributed Orders</h2>
        @forelse($attributedOrders as $o)
            <div class="flex justify-between items-center py-2 border-b border-surface-100 last:border-0 text-sm">
                <div><span class="font-medium">{{ $o->order_number }}</span> <span class="text-surface-500">— {{ $o->confirmed_at?->format('M d, H:i') }}</span></div>
                <span class="font-medium">${{ number_format($o->total, 2) }}</span>
            </div>
        @empty
            <p class="text-surface-500 text-sm">No attributed orders in this period.</p>
        @endforelse
    </div>
</div>
