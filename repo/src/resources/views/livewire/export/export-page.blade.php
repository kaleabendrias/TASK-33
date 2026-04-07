<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Export Data</h1>

    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 max-w-lg">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Export Type</label>
                <select wire:model="exportType" class="w-full rounded-lg border-surface-200 text-sm" aria-label="Select export type">
                    <option value="orders">Orders</option>
                    <option value="settlements">Settlements</option>
                    <option value="commissions">Commissions</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">From</label>
                    <input wire:model="dateFrom" type="date" class="w-full rounded-lg border-surface-200 text-sm"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">To</label>
                    <input wire:model="dateTo" type="date" class="w-full rounded-lg border-surface-200 text-sm"/>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-surface-700 mb-1">Format</label>
                <div class="flex gap-4" role="radiogroup" aria-label="Export format">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input wire:model="format" type="radio" value="csv" class="text-brand-600 focus:ring-brand-500"/> CSV
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input wire:model="format" type="radio" value="pdf" class="text-brand-600 focus:ring-brand-500"/> PDF
                    </label>
                </div>
            </div>
            <button wire:click="download" class="w-full py-2.5 px-4 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition flex items-center justify-center gap-2" tabindex="0">
                <x-icon name="arrow-down-tray" class="w-4 h-4"/> Download {{ strtoupper($format) }}
            </button>
        </div>
    </div>
</div>
