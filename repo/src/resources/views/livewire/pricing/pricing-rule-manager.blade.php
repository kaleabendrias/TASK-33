<div class="space-y-6">
    <h1 class="text-2xl font-bold">Pricing Rules</h1>

    @if ($message)
        <div class="rounded bg-blue-50 p-3 text-sm text-blue-800">{{ $message }}</div>
    @endif

    @if (!empty($errors_bag))
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800">
            @foreach ($errors_bag as $field => $msgs)
                @foreach ((array) $msgs as $msg)
                    <li><strong>{{ $field }}:</strong> {{ $msg }}</li>
                @endforeach
            @endforeach
        </ul>
    @endif

    <form wire:submit.prevent="save" class="grid grid-cols-1 gap-4 rounded border bg-white p-4 md:grid-cols-2">
        <label class="block">
            <span class="text-sm font-medium">Name</span>
            <input type="text" wire:model="name" class="mt-1 w-full rounded border px-3 py-2" required>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Bookable Item ID (optional)</span>
            <input type="number" wire:model="bookable_item_id" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Time slot start (HH:MM)</span>
            <input type="time" wire:model="time_slot_start" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Time slot end (HH:MM)</span>
            <input type="time" wire:model="time_slot_end" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Days of week (comma 1..7, Mon..Sun)</span>
            <input type="text" wire:model="days_of_week" placeholder="6,7" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Member tier</span>
            <select wire:model="member_tier" class="mt-1 w-full rounded border px-3 py-2">
                <option value="">— any —</option>
                <option value="standard">Standard</option>
                <option value="silver">Silver</option>
                <option value="gold">Gold</option>
                <option value="platinum">Platinum</option>
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Min headcount</span>
            <input type="number" wire:model="min_headcount" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Max headcount</span>
            <input type="number" wire:model="max_headcount" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Package code</span>
            <input type="text" wire:model="package_code" placeholder="SCHOOL" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Adjustment type</span>
            <select wire:model="adjustment_type" class="mt-1 w-full rounded border px-3 py-2">
                <option value="multiplier">Multiplier</option>
                <option value="fixed_price">Fixed price</option>
                <option value="discount_pct">Discount %</option>
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Adjustment value</span>
            <input type="number" step="0.0001" wire:model="adjustment_value" class="mt-1 w-full rounded border px-3 py-2" required>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Effective from</span>
            <input type="date" wire:model="effective_from" class="mt-1 w-full rounded border px-3 py-2" required>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Effective until</span>
            <input type="date" wire:model="effective_until" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="block">
            <span class="text-sm font-medium">Priority (lower wins)</span>
            <input type="number" wire:model="priority" class="mt-1 w-full rounded border px-3 py-2">
        </label>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active">
            <span class="text-sm font-medium">Active</span>
        </label>

        <div class="md:col-span-2 flex gap-2">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">
                {{ $editingId ? 'Update rule' : 'Create rule' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="rounded border px-4 py-2">Cancel</button>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Name</th>
                    <th class="px-3 py-2 text-left">Item</th>
                    <th class="px-3 py-2 text-left">Tier</th>
                    <th class="px-3 py-2 text-left">Pkg</th>
                    <th class="px-3 py-2 text-left">Slot</th>
                    <th class="px-3 py-2 text-left">Days</th>
                    <th class="px-3 py-2 text-left">Headcount</th>
                    <th class="px-3 py-2 text-left">Adj</th>
                    <th class="px-3 py-2 text-left">Prio</th>
                    <th class="px-3 py-2 text-left">Active</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rules as $r)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $r->id }}</td>
                        <td class="px-3 py-2">{{ $r->name }}</td>
                        <td class="px-3 py-2">{{ $r->bookable_item_id ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->member_tier ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->package_code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->time_slot_start }}{{ $r->time_slot_end ? '–' . $r->time_slot_end : '' }}</td>
                        <td class="px-3 py-2">{{ $r->days_of_week ?? '—' }}</td>
                        <td class="px-3 py-2">{{ ($r->min_headcount ?? '?') . '..' . ($r->max_headcount ?? '?') }}</td>
                        <td class="px-3 py-2">{{ $r->adjustment_type }}: {{ $r->adjustment_value }}</td>
                        <td class="px-3 py-2">{{ $r->priority }}</td>
                        <td class="px-3 py-2">{{ $r->is_active ? 'Y' : 'N' }}</td>
                        <td class="px-3 py-2 space-x-2">
                            <button type="button" wire:click="loadForEdit({{ $r->id }})" class="text-blue-600">Edit</button>
                            <button type="button" wire:click="delete({{ $r->id }})" class="text-red-600">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $rules->links() }}
</div>
