<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-track { background: transparent; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/crm.css') }}">
    @include('partials.select2-assets')
    @stack('styles')
</head>
<body class="h-full bg-slate-50 text-slate-700 antialiased">
<div x-data="{ sidebarOpen: false }" class="min-h-full">

    {{-- Overlay mobile --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col transform bg-slate-900 text-slate-300 shadow-xl transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white shadow-sm">
                <i class="bi bi-bezier2 text-lg"></i>
            </div>
            <div>
                <div class="text-sm font-semibold leading-tight text-white">AGC CRM</div>
                <div class="text-[11px] leading-tight text-slate-400">Sales Workspace</div>
            </div>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4 text-sm">
            @php
                $nav = [
                    ['dashboard', 'Dashboard', 'bi-grid-1x2'],
                    ['customers.index', 'Customers', 'bi-people'],
                    ['leads.index', 'Leads', 'bi-funnel'],
                    ['opportunities.index', 'Opportunities', 'bi-briefcase'],
                    ['activities.index', 'Activities', 'bi-calendar-check'],
                    ['quotations.index', 'Quotations', 'bi-file-earmark-text'],
                ];
            @endphp
            @foreach ($nav as [$route, $label, $icon])
                @php $active = request()->routeIs(Str::before($route, '.').'.*') || request()->routeIs($route); @endphp
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition
                          {{ $active ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                    <i class="bi {{ $icon }} text-base"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach

            @if (auth()->user()->isAdmin())
                <div class="px-3 pt-5 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administration</div>
                @php
                    $adminNav = [
                        ['contacts.index', 'Contacts', 'bi-person-lines-fill'],
                        ['templates.index', 'Quotation Templates', 'bi-file-earmark-richtext'],
                        ['users.index', 'Users', 'bi-person-gear'],
                    ];
                @endphp
                @foreach ($adminNav as [$route, $label, $icon])
                    @php $active = request()->routeIs(Str::before($route, '.').'.*'); @endphp
                    <a href="{{ route($route) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                              {{ $active ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                        <i class="bi {{ $icon }} text-base"></i>
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            @endif
        </nav>
    </aside>

    {{-- Konten utama --}}
    <div class="lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur-md lg:px-8">
            <button @click="sidebarOpen = true" class="text-slate-500 lg:hidden">
                <i class="bi bi-list text-2xl"></i>
            </button>

            <h1 class="text-base font-semibold text-slate-800">@yield('title', 'Dashboard')</h1>

            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('opportunities.create') }}"
                   class="hidden items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 sm:inline-flex">
                    <i class="bi bi-plus-lg"></i> New Opportunity
                </a>

                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                            {{ initials(auth()->user()->name) }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-medium leading-tight text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="block text-[11px] capitalize leading-tight text-slate-400">{{ auth()->user()->role }}</span>
                        </span>
                        <i class="bi bi-chevron-down text-xs text-slate-400"></i>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
                        <div class="px-4 py-2 text-xs text-slate-400 border-b border-slate-100">{{ auth()->user()->email }}</div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">
                            <i class="bi bi-person mr-2"></i> Profile & Password
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                <i class="bi bi-box-arrow-right mr-2"></i> Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 lg:px-8">
            @include('partials.flash')
            @auth
                @if (auth()->user()->isSales() && ! auth()->user()->hasDigitalSignature())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <span><i class="bi bi-pen"></i> Unggah tanda tangan digital agar bisa generate PDF penawaran.</span>
                        <a href="{{ route('profile.edit') }}" class="font-semibold text-amber-800 underline hover:text-amber-950">Ke Profile</a>
                    </div>
                @endif
            @endauth
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="{{ asset('js/crm-select2.js') }}"></script>
@stack('scripts')
</body>
</html>
