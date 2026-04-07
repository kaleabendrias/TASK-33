<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Dashboard</h1>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8" role="region" aria-label="Key metrics">
        <x-stat-card label="Available Items" :value="$totalItems" icon="calendar" />

        @if(in_array($role, ['staff','group-leader','admin']))
            <x-stat-card label="Today's Orders" :value="$todayOrders ?? 0" icon="clipboard" />
            <x-stat-card label="Active Orders"  :value="$activeOrders ?? 0" icon="check" />
            <x-stat-card label="Month Revenue"  :value="'$'.number_format($monthRevenue ?? 0, 2)" icon="banknotes" />
        @endif
    </div>

    @if(in_array($role, ['group-leader','admin']))
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <x-stat-card label="My Attributed Orders" :value="$myOrders ?? 0" icon="chart-bar" />
        <x-stat-card label="Earned Commissions"   :value="'$'.number_format($myCommissions ?? 0, 2)" icon="banknotes" />
        @if($role === 'admin')
            <x-stat-card label="Pending Settlements" :value="$pendingSettlements ?? 0" icon="exclamation-triangle" />
        @endif
    </div>
    @endif

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold text-surface-800 mb-4">Quick Actions</h2>
        <div class="flex flex-wrap gap-3">
            @if(in_array($role, ['staff','group-leader','admin']))
                <a href="/bookings/create" class="inline-flex items-center px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition focus-visible:ring-2 focus-visible:ring-brand-500" tabindex="0">
                    <x-icon name="plus" class="w-4 h-4 mr-2"/> New Booking
                </a>
                <a href="/orders" class="inline-flex items-center px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm font-medium hover:bg-surface-200 transition" tabindex="0">
                    <x-icon name="clipboard" class="w-4 h-4 mr-2"/> View Orders
                </a>
            @endif
            @if(in_array($role, ['group-leader','admin']))
                <a href="/commissions" class="inline-flex items-center px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm font-medium hover:bg-surface-200 transition" tabindex="0">
                    <x-icon name="chart-bar" class="w-4 h-4 mr-2"/> Commission Report
                </a>
            @endif
            @if($role === 'admin')
                <a href="/settlements" class="inline-flex items-center px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm font-medium hover:bg-surface-200 transition" tabindex="0">
                    <x-icon name="banknotes" class="w-4 h-4 mr-2"/> Settlements
                </a>
            @endif
            <a href="/exports" class="inline-flex items-center px-4 py-2 rounded-lg bg-surface-100 text-surface-700 text-sm font-medium hover:bg-surface-200 transition" tabindex="0">
                <x-icon name="arrow-down-tray" class="w-4 h-4 mr-2"/> Exports
            </a>
        </div>
    </div>
</div>
