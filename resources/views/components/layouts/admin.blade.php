<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head', ['title' => ($title ?? 'Admin Panel') . ' - Scholar Admin'])
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-[#0c131a] text-zinc-900 dark:text-zinc-100 font-sans antialiased">
    <!-- FLUX SIDEBAR -->
    <flux:sidebar stashable sticky class="bg-white dark:bg-[#111c26] border-r border-zinc-200/90 dark:border-zinc-800/80">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <!-- BRAND HEADER -->
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-2 py-1 overflow-hidden group">
            <div class="size-9 rounded-xl bg-gradient-to-tr from-brand to-brand-600 flex items-center justify-center text-white font-bold shadow-md shadow-brand/20 shrink-0">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div class="flex flex-col truncate">
                <span class="text-sm font-bold tracking-tight text-dark-navy dark:text-white flex items-center gap-1">
                    Scholar<span class="text-brand">Admin</span>
                </span>
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium">Control Center</span>
            </div>
        </a>

        <!-- NAVLIST -->
        <flux:navlist variant="outline">
            <flux:navlist.group heading="Core Management" class="grid">
                <flux:navlist.item icon="home" href="{{ route('admin.dashboard') }}" :current="request()->routeIs('admin.dashboard')">Dashboard</flux:navlist.item>
                <flux:navlist.item icon="document-text" href="{{ route('admin.publications.index') }}" :current="request()->routeIs('admin.publications.*')">Publications</flux:navlist.item>
                
                <!-- Users Section with Expandable Submenu -->
                <div x-data="{ open: {{ request()->routeIs('admin.users.*') ? 'true' : 'false' }} }" class="space-y-0.5">
                    <button 
                        type="button" 
                        @click="open = !open" 
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition cursor-pointer {{ request()->routeIs('admin.users.*') ? 'bg-zinc-100 dark:bg-zinc-800/80 text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white' }}"
                    >
                        <div class="flex items-center gap-2.5">
                            <flux:icon name="users" class="size-4 shrink-0 {{ request()->routeIs('admin.users.*') ? 'text-brand' : 'text-zinc-400' }}" />
                            <span class="font-medium text-[13px]">Users</span>
                        </div>
                        <flux:icon name="chevron-right" class="size-3.5 transition-transform duration-200" ::class="open ? 'rotate-90 text-brand' : 'text-zinc-400'" />
                    </button>

                    <div 
                        x-show="open" 
                        x-cloak 
                        x-collapse
                        class="pl-3 pr-1 space-y-0.5 border-l-2 border-zinc-200 dark:border-zinc-800 ml-4 my-1"
                    >
                        <a 
                            href="{{ route('admin.users.index') }}" 
                            wire:navigate 
                            class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('admin.users.*') && (!request()->has('type') || request()->query('type') === 'all') ? 'bg-brand/10 text-brand font-bold dark:bg-brand/20' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100/70 dark:hover:bg-zinc-800/60' }}"
                        >
                            <flux:icon name="user-group" class="size-3.5 shrink-0" />
                            <span>All Users</span>
                        </a>
                        <a 
                            href="{{ route('admin.users.index', ['type' => 'publisher']) }}" 
                            wire:navigate 
                            class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium transition {{ request()->query('type') === 'publisher' ? 'bg-brand/10 text-brand font-bold dark:bg-brand/20' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100/70 dark:hover:bg-zinc-800/60' }}"
                        >
                            <flux:icon name="building-office-2" class="size-3.5 shrink-0" />
                            <span>Publisher Users</span>
                        </a>
                        <a 
                            href="{{ route('admin.users.index', ['type' => 'python']) }}" 
                            wire:navigate 
                            class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium transition {{ request()->query('type') === 'python' ? 'bg-brand/10 text-brand font-bold dark:bg-brand/20' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100/70 dark:hover:bg-zinc-800/60' }}"
                        >
                            <flux:icon name="code-bracket" class="size-3.5 shrink-0" />
                            <span>Python Users</span>
                        </a>
                        <a 
                            href="{{ route('admin.users.index', ['type' => 'notified']) }}" 
                            wire:navigate 
                            class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium transition {{ request()->query('type') === 'notified' ? 'bg-brand/10 text-brand font-bold dark:bg-brand/20' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100/70 dark:hover:bg-zinc-800/60' }}"
                        >
                            <flux:icon name="bell" class="size-3.5 shrink-0" />
                            <span>Notified Users List</span>
                        </a>
                        <a 
                            href="{{ route('admin.users.index', ['type' => 'approval']) }}" 
                            wire:navigate 
                            class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium transition {{ request()->query('type') === 'approval' ? 'bg-brand/10 text-brand font-bold dark:bg-brand/20' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-100/70 dark:hover:bg-zinc-800/60' }}"
                        >
                            <flux:icon name="shield-check" class="size-3.5 shrink-0" />
                            <span>Request For Account Approval</span>
                        </a>
                    </div>
                </div>
            </flux:navlist.group>

            <flux:navlist.group heading="Database Tools" class="grid">
                <flux:navlist.item icon="circle-stack" href="{{ route('admin.database-fixing.index') }}" :current="request()->routeIs('admin.database-fixing.*')">Database Fixing</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.group heading="Application" class="grid">
                <flux:navlist.item icon="arrow-top-right-on-square" href="{{ route('articles.index') }}" target="_blank">Public Portal</flux:navlist.item>
                <flux:navlist.item icon="user-circle" href="{{ route('dashboard') }}">User Dashboard</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <!-- FOOTER USER PROFILE & LOGOUT -->
        <div class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800">
            <div class="flex items-center gap-3 p-1 rounded-xl">
                <div class="size-9 rounded-full bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-xs shrink-0">
                    {{ auth()->user()?->initials() ?? 'AD' }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-zinc-900 dark:text-white truncate">
                        {{ auth()->user()?->name ?? 'Administrator' }}
                    </p>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                        {{ auth()->user()?->email ?? 'admin@system.local' }}
                    </p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-1.5 text-zinc-400 hover:text-danger hover:bg-danger-light/10 rounded-lg transition cursor-pointer" title="Sign out">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </flux:sidebar>

    <!-- STICKY TOPBAR NAVBAR -->
    <flux:header sticky class="h-16 bg-white/90 dark:bg-[#111c26]/90 backdrop-blur-md border-b border-zinc-200/90 dark:border-zinc-800/80 px-4 sm:px-6 lg:px-8">
        <!-- Mobile Sidebar Toggle -->
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <!-- Breadcrumbs & Section Title -->
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-1.5 font-semibold text-zinc-500 dark:text-zinc-400 hover:text-brand transition">
                <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Admin</span>
            </a>
            <span class="text-zinc-300 dark:text-zinc-600">/</span>
            <span class="font-bold text-dark-navy dark:text-white capitalize">
                {{ request()->segment(2) ? str_replace('-', ' ', request()->segment(2)) : 'Dashboard' }}
            </span>
        </div>

        <!-- Global Search Bar -->
        <div class="hidden md:flex relative items-center ml-6 flex-1 max-w-sm">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </span>
            <input 
                type="text" 
                placeholder="Search publications, scholars, tools..." 
                class="w-full pl-9 pr-12 py-1.5 text-xs bg-zinc-100/80 dark:bg-zinc-800/80 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 border border-zinc-200/80 dark:border-zinc-700/80 rounded-xl focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
            />
            <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                <kbd class="px-1.5 py-0.5 text-[10px] font-mono text-zinc-400 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded shadow-xs">Ctrl K</kbd>
            </span>
        </div>

        <flux:spacer />

        <!-- Header Right Actions & Profile -->
        <div class="flex items-center gap-3" x-data="{ userMenuOpen: false }">
            <!-- Live Database Connection Indicator -->
            <span class="hidden xl:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                MongoDB Live
            </span>

            <!-- Public Portal Link -->
            <a 
                href="{{ route('articles.index') }}" 
                target="_blank"
                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition cursor-pointer"
                title="Open Public Scholar Portal"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <span>Live Portal</span>
            </a>

            <!-- Admin Badge -->
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-50 dark:bg-brand/10 text-brand border border-brand/20">
                <span class="size-1.5 rounded-full bg-brand animate-ping"></span>
                Admin
            </span>

            <!-- User Profile Dropdown -->
            <div class="relative" @click.outside="userMenuOpen = false">
                <button 
                    @click="userMenuOpen = !userMenuOpen"
                    class="flex items-center gap-2 p-1 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition cursor-pointer"
                >
                    <div class="size-8 rounded-full bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-xs ring-2 ring-brand/20">
                        {{ auth()->user()?->initials() ?? 'AD' }}
                    </div>
                    <div class="hidden md:flex flex-col text-left">
                        <span class="text-xs font-bold text-zinc-900 dark:text-white leading-tight">
                            {{ auth()->user()?->name ?? 'Admin' }}
                        </span>
                        <span class="text-[10px] text-zinc-400 leading-none">Super Administrator</span>
                    </div>
                    <svg class="size-3.5 text-zinc-400 transition-transform duration-200" :class="userMenuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Menu -->
                <div 
                    x-show="userMenuOpen" 
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 mt-2 w-60 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl py-2 z-50 divide-y divide-zinc-100 dark:divide-zinc-800"
                    style="display: none;"
                >
                    <!-- User Header -->
                    <div class="px-4 py-2.5">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ auth()->user()?->name ?? 'Admin' }}</p>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">{{ auth()->user()?->email ?? 'admin@system.local' }}</p>
                        <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-semibold bg-brand-50 dark:bg-brand/20 text-brand rounded-full">Admin Level 1</span>
                    </div>

                    <!-- Links -->
                    <div class="py-1 text-xs">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 hover:text-brand transition">
                            <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Scholar User View
                        </a>
                        <a href="{{ route('settings.profile') }}" class="flex items-center gap-2.5 px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 hover:text-brand transition">
                            <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Account Settings
                        </a>
                        <a href="{{ route('admin.database-fixing.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 hover:text-brand transition">
                            <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            Database Fixing
                        </a>
                    </div>

                    <!-- Logout Form -->
                    <div class="py-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-danger hover:bg-danger-light/10 transition text-left cursor-pointer">
                                <svg class="size-4 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </flux:header>

    <!-- MAIN CONTENT -->
    <flux:main class="p-4 sm:p-6 lg:p-8">
        {{ $slot }}
    </flux:main>

    @fluxScripts
</body>
</html>
