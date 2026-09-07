<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    {{-- Website icon & logo --}}
    <link rel="icon" type="image/png" href="{{ asset('images/logo-crm.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-crm.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo-crm.png') }}">
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
<div x-data="{
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('crm_sidebar_collapsed') === '1',
        toggleSidebar() {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('crm_sidebar_collapsed', this.sidebarCollapsed ? '1' : '0');
            } else {
                this.sidebarOpen = !this.sidebarOpen;
            }
        },
     }"
     class="min-h-full">

    {{-- Overlay mobile --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="{
               'translate-x-0': sidebarOpen,
               '-translate-x-full': !sidebarOpen,
               'crm-sidebar--collapsed': sidebarCollapsed,
               'lg:w-16': sidebarCollapsed,
               'lg:w-64': !sidebarCollapsed,
           }"
           class="crm-sidebar fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 text-slate-300 shadow-xl transition-all duration-200 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white shadow-sm">
                <img src="{{ asset('images/logo-crm.png') }}" alt="AGC CRM"
                     class="h-7 w-7 object-contain"
                     loading="eager">
            </div>
            <div class="crm-sidebar-label min-w-0 flex-1">
                <div class="text-sm font-semibold leading-tight text-white">AGC CRM</div>
                <div class="text-[11px] leading-tight text-slate-400">Sales Workspace</div>
            </div>
            <button type="button" @click="toggleSidebar()"
                    class="crm-sidebar-label hidden rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:inline-flex"
                    title="Ciutkan sidebar">
                <i class="bi bi-layout-sidebar-inset text-lg"></i>
            </button>
            <button type="button" @click="sidebarOpen = false"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
                    title="Tutup">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-x-hidden overflow-y-auto px-2 py-4 text-sm">
            @php
                $user = auth()->user();
                $nav = [
                    ['dashboard', 'Dashboard', 'bi-grid-1x2'],
                ];
                if ($user->canViewCustomers()) {
                    $nav[] = ['customers.index', 'Customers', 'bi-people'];
                }
                if ($user->canCreateOpportunity() || ($user->canViewOpportunities() && ! $user->isPurchasing() && ! $user->isFinance())) {
                    $nav[] = ['leads.index', 'Leads', 'bi-funnel'];
                }
                if ($user->canViewOpportunities() && ! $user->isFinance()) {
                    $nav[] = $user->isPurchasing()
                        ? ['opportunities.index', 'Closed Won', 'bi-trophy']
                        : ['opportunities.index', 'Opportunities', 'bi-briefcase'];
                }
                if ($user->canViewSalesOrders()) {
                    $nav[] = ['sales-orders.index', 'SO', 'bi-receipt'];
                }
                if ($user->canViewPurchaseOrders()) {
                    $nav[] = ['purchase-orders.index', 'PO', 'bi-cart-check'];
                }
                $catalogNav = [];
                if ($user->canManageBrands() && ! $user->canAccessAdministration()) {
                    $catalogNav[] = ['brands.index', 'Brands', 'bi-tags'];
                }
                if ($user->canManageCategories() && ! $user->canAccessAdministration()) {
                    $catalogNav[] = ['categories.index', 'Categories', 'bi-folder'];
                }
                if ($user->canManageVendors() && ! $user->canAccessAdministration()) {
                    $catalogNav[] = ['vendors.index', 'Vendors', 'bi-truck'];
                }
                if ($user->canManageVendorStocks()) {
                    $nav[] = ['vendor-stocks.index', 'Ketersediaan Vendor', 'bi-boxes'];
                }
                if ($user->canCreateOpportunity() || $user->isAdmin()) {
                    $nav[] = ['activities.index', 'Activities', 'bi-calendar-check'];
                }
                if ($user->canCreateQuotation()) {
                    $nav[] = ['quotations.index', 'Quotations', 'bi-file-earmark-text'];
                }
            @endphp
            @foreach ($nav as [$route, $label, $icon])
                @php $active = request()->routeIs(Str::before($route, '.').'.*') || request()->routeIs($route); @endphp
                <a href="{{ route($route) }}" title="{{ $label }}"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition
                          {{ $active ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                    <i class="bi {{ $icon }} shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">{{ $label }}</span>
                </a>
            @endforeach

            @if (! empty($catalogNav))
                <div class="crm-sidebar-label px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Master Data
                </div>
                @foreach ($catalogNav as [$route, $label, $icon])
                    @php $active = request()->routeIs(Str::before($route, '.').'.*') || request()->routeIs($route); @endphp
                    <a href="{{ route($route) }}" title="{{ $label }}"
                       class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition
                              {{ $active ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <i class="bi {{ $icon }} shrink-0 text-base"></i>
                        <span class="crm-sidebar-label truncate">{{ $label }}</span>
                    </a>
                @endforeach
            @endif

            @if (auth()->user()->canAccessAdministration())
                <div class="crm-sidebar-label px-3 pt-5 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administration</div>
                @php
                    $userRoles = \App\Models\User::ROLES;
                    $usersMenuOpen = request()->routeIs('users.*');
                    $routeUser = request()->route('user');
                    $activeUserRole = request()->query('role')
                        ?? (is_object($routeUser) ? $routeUser->role : null)
                        ?? (request()->routeIs('users.create') ? request()->query('role', \App\Models\User::ROLE_SALES) : null);
                    $settingsOpen = request()->routeIs('settings.*');
                @endphp

                <a href="{{ route('contacts.index') }}" title="Contacts"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ request()->routeIs('contacts.*') ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-person-lines-fill shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">Contacts</span>
                </a>
                <a href="{{ route('brands.index') }}" title="Brands"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ request()->routeIs('brands.*') ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-tags shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">Brands</span>
                </a>
                <a href="{{ route('categories.index') }}" title="Categories"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ request()->routeIs('categories.*') ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-folder shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">Categories</span>
                </a>
                <a href="{{ route('vendors.index') }}" title="Vendors"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ request()->routeIs('vendors.*') ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-truck shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">Vendors</span>
                </a>
                <a href="{{ route('sales-order-logs.index') }}" title="Log SO"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ request()->routeIs('sales-order-logs.*') ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-journal-text shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">Log SO</span>
                </a>
                <a href="{{ route('templates.index') }}" title="Quotation Templates"
                   class="crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ request()->routeIs('templates.*') ? 'bg-brand-600 text-white shadow' : 'hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-file-earmark-richtext shrink-0 text-base"></i>
                    <span class="crm-sidebar-label truncate">Quotation Templates</span>
                </a>

                {{-- Users: collapsed = direct link; expanded = submenu --}}
                <a href="{{ route('users.index', ['role' => \App\Models\User::ROLE_SALES]) }}" title="Users"
                   class="crm-sidebar-collapsed-only crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ $usersMenuOpen ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-person-gear shrink-0 text-base"></i>
                </a>
                <div x-data="{ open: {{ $usersMenuOpen ? 'true' : 'false' }} }" class="crm-sidebar-expanded-only space-y-0.5">
                    <button type="button" @click="open = !open"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition
                                   {{ $usersMenuOpen ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <i class="bi bi-person-gear shrink-0 text-base"></i>
                        <span class="flex-1 text-left">Users</span>
                        <i class="bi text-xs transition-transform" :class="open ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                    </button>
                    <div x-show="open" x-cloak class="ml-3 space-y-0.5 border-l border-white/10 pl-3">
                        @foreach ($userRoles as $roleKey => $roleLabel)
                            @php $roleActive = $usersMenuOpen && $activeUserRole === $roleKey; @endphp
                            <a href="{{ route('users.index', ['role' => $roleKey]) }}"
                               class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                      {{ $roleActive ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                                <i class="bi bi-person text-xs"></i>
                                <span>{{ $roleLabel }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Settings: collapsed = direct link; expanded = submenu --}}
                <a href="{{ route('settings.edit') }}" title="Settings"
                   class="crm-sidebar-collapsed-only crm-sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ $settingsOpen ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                    <i class="bi bi-gear shrink-0 text-base"></i>
                </a>
                <div x-data="{ open: {{ $settingsOpen ? 'true' : 'false' }} }" class="crm-sidebar-expanded-only space-y-0.5">
                    <button type="button" @click="open = !open"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition
                                   {{ $settingsOpen ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <i class="bi bi-gear shrink-0 text-base"></i>
                        <span class="flex-1 text-left">Settings</span>
                        <i class="bi text-xs transition-transform" :class="open ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                    </button>
                    <div x-show="open" x-cloak class="ml-3 space-y-0.5 border-l border-white/10 pl-3">
                        <a href="{{ route('settings.edit') }}"
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                  {{ request()->routeIs('settings.edit') || request()->routeIs('settings.update') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <i class="bi bi-percent text-xs"></i>
                            <span>Tax</span>
                        </a>
                        <a href="{{ route('settings.margin.edit') }}"
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                  {{ request()->routeIs('settings.margin.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <i class="bi bi-graph-up-arrow text-xs"></i>
                            <span>Margin</span>
                        </a>
                        <a href="{{ route('settings.po.edit') }}"
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                  {{ request()->routeIs('settings.po.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <i class="bi bi-cart-check text-xs"></i>
                            <span>PO</span>
                        </a>
                        <a href="{{ route('settings.terms.edit') }}"
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                  {{ request()->routeIs('settings.terms.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <i class="bi bi-file-text text-xs"></i>
                            <span>Terms QO</span>
                        </a>
                        <a href="{{ route('settings.shipping.edit') }}"
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                  {{ request()->routeIs('settings.shipping.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <i class="bi bi-truck text-xs"></i>
                            <span>Free Ongkir</span>
                        </a>
                        <a href="{{ route('settings.wilayah.index') }}"
                           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition
                                  {{ request()->routeIs('settings.wilayah.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <i class="bi bi-geo-alt text-xs"></i>
                            <span>Wilayah</span>
                        </a>
                    </div>
                </div>
            @endif
        </nav>
    </aside>

    {{-- Konten utama --}}
    <div class="transition-[padding] duration-200" :class="sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64'">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur-md lg:px-8">
            <button type="button" @click="toggleSidebar()"
                    class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    :title="sidebarCollapsed ? 'Perlebar sidebar' : 'Ciutkan sidebar'">
                <i class="bi text-2xl" :class="sidebarCollapsed ? 'bi-layout-sidebar' : 'bi-list'"></i>
            </button>

            <h1 class="text-base font-semibold text-slate-800">@yield('title', 'Dashboard')</h1>

            <div class="ml-auto flex items-center gap-3">
                @if (auth()->user()->canCreateOpportunity())
                    <a href="{{ route('opportunities.create') }}"
                       class="hidden items-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 sm:inline-flex">
                        <i class="bi bi-plus-lg"></i> New Opportunity
                    </a>
                @endif

                @include('partials.notification-bell')

                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                            {{ initials(auth()->user()->name) }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-medium leading-tight text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="block text-[11px] leading-tight text-slate-400">{{ auth()->user()->roleLabel() }}</span>
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

        <main class="mx-auto w-full max-w-[1600px] px-4 py-6 lg:px-8">
            @include('partials.flash')
            @auth
                @if (auth()->user()->isSales() && ! auth()->user()->hasDigitalSignature())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <span>
                            <i class="bi bi-pen"></i>
                            Unggah 3 tanda tangan (AGC, EPS, PSI) agar bisa generate PDF penawaran.
                            @if (count(auth()->user()->missingSignatureLabels()) > 0)
                                Belum: <strong>{{ implode(', ', auth()->user()->missingSignatureLabels()) }}</strong>.
                            @endif
                        </span>
                        <a href="{{ route('profile.edit') }}" class="font-semibold text-amber-800 underline hover:text-amber-950">Ke Profile</a>
                    </div>
                @endif
            @endauth
            @yield('content')
        </main>
    </div>
</div>

@include('partials.notification-popup')

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="{{ asset('js/crm-number.js') }}"></script>
<script src="{{ asset('js/crm-select2.js') }}?v=20260826b"></script>
@stack('scripts')
</body>
</html>
