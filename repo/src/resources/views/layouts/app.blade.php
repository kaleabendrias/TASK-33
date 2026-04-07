<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>{{ $title ?? 'ServicePlatform' }}</title>

    {{-- Pre-compiled Tailwind utility CSS — fully offline, no Play CDN.
         Built once at image-bake time and served as a static asset. --}}
    <link rel="stylesheet" href="/assets/css/tailwind.css">

    {{-- High-contrast accessible defaults --}}
    <style>
        /* Ensure WCAG AA (4.5:1) contrast for body text */
        body { color: #1e293b; background: #f8fafc; }
        /* Focus ring for keyboard navigation */
        *:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; border-radius: 4px; }
        /* Skip-to-content link */
        .skip-link { position:absolute; left:-9999px; top:auto; }
        .skip-link:focus { left:1rem; top:1rem; z-index:9999; background:#1e3a8a; color:#fff; padding:.5rem 1rem; border-radius:.375rem; }
        /* Reduce motion for accessibility */
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration:0.01ms!important; transition-duration:0.01ms!important; } }
        /* Lazy image placeholder */
        img[loading="lazy"] { background:#e2e8f0; min-height:2rem; }
    </style>

    @livewireStyles
</head>
<body class="h-full font-sans antialiased" x-data="{ sidebarOpen: false }">

    {{-- Skip to main content – keyboard a11y --}}
    <a href="#main-content" class="skip-link" tabindex="0">Skip to main content</a>

    @if(session('jwt_token'))
    <div class="flex h-full">
        {{-- Sidebar (desktop: always visible, mobile: slide-over) --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-surface-900 text-white transform transition-transform duration-200 lg:translate-x-0 lg:static lg:inset-auto"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            role="navigation"
            aria-label="Main navigation"
        >
            <div class="flex items-center h-16 px-4 border-b border-surface-700">
                <span class="text-lg font-bold tracking-tight">ServicePlatform</span>
            </div>

            <nav class="mt-4 space-y-1 px-2" aria-label="Primary">
                @php $role = session('auth_role', 'user'); @endphp

                <x-nav-link href="/dashboard" icon="home" label="Dashboard" />

                <x-nav-link href="/bookings" icon="calendar" label="Bookings" />
                <x-nav-link href="/orders" icon="clipboard" label="Orders" />

                {{-- Settlements are visible to staff/group-leader/admin (read-only for staff). --}}
                @if(in_array($role, ['staff','group-leader','admin']))
                    <x-nav-link href="/settlements" icon="banknotes" label="Settlements" />
                @endif
                {{-- Commissions remain a group-leader+ concept. --}}
                @if(in_array($role, ['group-leader','admin']))
                    <x-nav-link href="/commissions" icon="chart-bar" label="Commissions" />
                @endif

                <x-nav-link href="/exports" icon="arrow-down-tray" label="Exports" />

                <x-nav-link href="/profile" icon="user-circle" label="Profile" />

                <div class="pt-4 mt-4 border-t border-surface-700">
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="flex items-center w-full px-3 py-2 text-sm text-surface-200 rounded-lg hover:bg-surface-700 transition" tabindex="0">
                            <x-icon name="arrow-right-on-rectangle" class="w-5 h-5 mr-3 opacity-60"/>
                            Sign Out
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        {{-- Sidebar overlay (mobile) --}}
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-50"
            x-transition:leave="transition-opacity duration-200" x-transition:leave-start="opacity-50" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-30 bg-black lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Main content area --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            {{-- Top bar --}}
            <header class="flex items-center h-16 px-4 bg-white border-b border-surface-200 shrink-0" role="banner">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 -ml-2 text-surface-700 hover:bg-surface-100 rounded-lg"
                    aria-label="Toggle navigation menu"
                    tabindex="0"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="ml-auto flex items-center gap-3">
                    <span class="text-sm font-medium text-surface-700" aria-label="Current user">
                        {{ session('auth_user_name', 'User') }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800" role="status">
                        {{ ucfirst(session('auth_role', 'user')) }}
                    </span>
                </div>
            </header>

            {{-- Page content --}}
            <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8" role="main" tabindex="-1">
                @if(session('flash_success'))
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm" role="alert">
                        {{ session('flash_success') }}
                    </div>
                @endif
                @if(session('flash_error'))
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm" role="alert">
                        {{ session('flash_error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
    @else {{-- Guest layout --}}
        <main class="min-h-screen flex items-center justify-center bg-surface-50" role="main">
            {{ $slot }}
        </main>
    @endif

    {{-- Note: Livewire 3 ships its own bundled Alpine instance via @livewireScripts.
         Loading a separate Alpine here would trigger the "multiple instances of
         Alpine running" warning, so we DO NOT include /assets/js/alpine.js. --}}
    @livewireScripts
</body>
</html>
