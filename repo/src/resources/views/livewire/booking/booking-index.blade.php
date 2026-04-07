<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <h1 class="text-2xl font-bold text-surface-900">Available Items</h1>
        <a href="/bookings/create" class="inline-flex items-center px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition" tabindex="0">
            <x-icon name="plus" class="w-4 h-4 mr-2"/> New Booking
        </a>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-6" role="search" aria-label="Filter bookable items">
        <div class="relative flex-1">
            <x-icon name="magnifying-glass" class="absolute left-3 top-2.5 w-4 h-4 text-surface-400"/>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Search items…"
                class="w-full pl-9 pr-3 py-2 rounded-lg border-surface-200 text-sm focus:border-brand-500 focus:ring-brand-500"
                aria-label="Search bookable items"
            />
        </div>
        <select
            wire:model.live="typeFilter"
            class="rounded-lg border-surface-200 text-sm focus:border-brand-500 focus:ring-brand-500 w-full sm:w-48"
            aria-label="Filter by type"
        >
            <option value="">All Types</option>
            @foreach(['lab','room','workstation','equipment','consumable'] as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>

    {{-- Item Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($items as $itemRaw)
            @php
                // BookingIndex now consumes the API JSON payload directly,
                // so each $item is an associative array. Cast to object so
                // existing field-access syntax keeps working.
                $item = (object) (is_array($itemRaw) ? $itemRaw : $itemRaw->toArray());
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-hidden hover:shadow-md transition" role="article" aria-label="{{ $item->name ?? '' }}">
                @if($item->image_path ?? null)
                    <img loading="lazy" src="{{ $item->image_path }}" alt="{{ $item->name }}" class="w-full h-40 object-cover"/>
                @else
                    <div class="w-full h-40 bg-surface-100 flex items-center justify-center">
                        <x-icon name="calendar" class="w-12 h-12 text-surface-300"/>
                    </div>
                @endif
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-50 text-brand-700">{{ ucfirst($item->type ?? '') }}</span>
                        @if($item->location ?? null)<span class="text-xs text-surface-500">{{ $item->location }}</span>@endif
                    </div>
                    <h3 class="font-semibold text-surface-900 mb-1">{{ $item->name ?? '' }}</h3>
                    <p class="text-sm text-surface-500 line-clamp-2 mb-3">{{ $item->description ?? 'No description' }}</p>
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-surface-700">
                            @if(($item->type ?? null) === 'consumable')
                                ${{ number_format((float) ($item->unit_price ?? 0), 2) }}/unit
                            @elseif(($item->hourly_rate ?? 0) > 0)
                                ${{ number_format((float) $item->hourly_rate, 2) }}/hr
                            @else
                                ${{ number_format((float) ($item->daily_rate ?? 0), 2) }}/day
                            @endif
                        </div>
                        <a href="/bookings/create?item={{ $item->id ?? '' }}" class="text-sm font-medium text-brand-600 hover:text-brand-700" tabindex="0">Book →</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 text-surface-500">No items found.</div>
        @endforelse
    </div>

    <div class="mt-6" aria-label="Pagination">{{ $items->links() }}</div>
</div>
