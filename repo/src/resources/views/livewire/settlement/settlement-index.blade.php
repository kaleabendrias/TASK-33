<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Settlements & Reconciliation</h1>

    @if($message)<div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-green-700 text-sm" role="status">{{ $message }}</div>@endif

    @php
        // The Generate / Finalize controls are administrative; non-admins
        // see ONLY the read-only summary list. This eliminates the 403
        // friction non-admins used to hit when clicking buttons they
        // weren't authorized to use.
        $currentUser = auth()->user() ?? request()->attributes->get('auth_user');
        $isAdmin = $currentUser && $currentUser->isAdmin();
    @endphp

    {{-- Generate (admin only) --}}
    @if($isAdmin)
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Generate Settlement</h2>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Period Start</label>
                <input wire:model="periodStart" type="date" class="rounded-lg border-surface-200 text-sm"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Period End</label>
                <input wire:model="periodEnd" type="date" class="rounded-lg border-surface-200 text-sm"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Cycle Type</label>
                <select wire:model="cycleType" class="rounded-lg border-surface-200 text-sm">
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Biweekly</option>
                </select>
            </div>
            <button wire:click="generate" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition" tabindex="0">Generate & Reconcile</button>
        </div>
    </div>
    @endif

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-x-auto">
        <table class="w-full text-sm" role="table">
            <thead><tr class="border-b text-left text-surface-500 text-xs uppercase"><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Period</th><th class="px-4 py-3 text-right">Gross</th><th class="px-4 py-3 text-right">Refunds</th><th class="px-4 py-3 text-right">Net</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody>
                @forelse($settlements as $s)
                <tr class="border-b border-surface-100">
                    <td class="px-4 py-3 font-medium">{{ $s->reference }}</td>
                    <td class="px-4 py-3">{{ $s->period_start->format('M d') }} – {{ $s->period_end->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-right">${{ number_format($s->gross_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right text-red-600">-${{ number_format($s->refund_total, 2) }}</td>
                    <td class="px-4 py-3 text-right font-bold">${{ number_format($s->net_amount, 2) }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $s->status === 'finalized' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ ucfirst($s->status) }}</span></td>
                    <td class="px-4 py-3">
                        @if($isAdmin && $s->status !== 'finalized')
                            <button wire:click="finalize({{ $s->id }})" class="text-brand-600 hover:underline text-xs" tabindex="0">Finalize</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-surface-500">No settlements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $settlements->links() }}</div>
</div>
