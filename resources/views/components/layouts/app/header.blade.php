<div 
    x-data="{ 
        mobileMenuOpen: false, 
        searchOpen: false,
        userDropdownOpen: false,
        pubDropdownOpen: false,
        mentorDropdownOpen: false,
        mobilePubOpen: false,
        mobileMentorOpen: false
    }"
    class="sticky top-0 z-50 w-full bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 shadow-xs"
>
    <!-- Impersonation Notice (Browsing As User) -->
    <x-impersonation-banner />

    <header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        
        <!-- Left Section: Logo & Search -->
        <div class="flex items-center gap-6 flex-1 min-w-0">
            <!-- Brand Logo & Tagline -->
            <a href="{{ url('/') }}" class="flex flex-col items-center shrink-0 group" wire:navigate>
                <div class="flex items-center gap-2">
                    <x-app-logo class="h-8 w-auto" />
                </div>
                <span class="text-[11px] font-medium text-sky-600 dark:text-sky-400 leading-none mt-0.5 tracking-tight">
                    True scholar network
                </span>
            </a>

            <!-- Desktop Search Bar -->
            <div class="hidden md:flex relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                    <i class="fas fa-search text-xs"></i>
                </span>
                <input 
                    type="text" 
                    placeholder="Search for researchers, publications, questions..." 
                    class="w-full pl-10 pr-4 py-2 text-sm bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 border border-zinc-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white dark:focus:bg-zinc-900 focus:ring-3 focus:ring-sky-500/15 transition-all"
                />
            </div>
        </div>

        <!-- Desktop Navigation Bar -->
        <nav class="hidden lg:flex items-center gap-1.5">
            
            <!-- 1. Publications Dropdown -->
            <div 
                class="relative py-2" 
                @mouseenter="pubDropdownOpen = true" 
                @mouseleave="pubDropdownOpen = false"
                @click.outside="pubDropdownOpen = false"
            >
                <button 
                    @click="pubDropdownOpen = !pubDropdownOpen"
                    class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors cursor-pointer"
                    :class="pubDropdownOpen || {{ request()->is('articles*') ? 'true' : 'false' }} ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : ''"
                >
                    <i class="fas fa-file-alt text-[20px] leading-6"></i>
                    <span class="flex items-center gap-0.5 font-medium">
                        Publications
                        <span class="text-[9px] transition-transform inline-block ml-0.5" :class="pubDropdownOpen ? 'rotate-180' : ''">▼</span>
                    </span>
                </button>

                <!-- Publications Menu -->
                <div 
                    x-show="pubDropdownOpen" 
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute top-full left-0 w-52 bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700/80 rounded-2xl shadow-xl p-2 z-50 origin-top-left"
                    style="display: none;"
                >
                    <!-- Article List -->
                    <a href="{{ url('articles') }}" @click="pubDropdownOpen = false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-sky-600 dark:hover:text-sky-400 rounded-xl transition-colors" wire:navigate>
                        <i class="fas fa-list text-zinc-400 w-5 text-center shrink-0"></i>
                        <span>Article List</span>
                    </a>

                    <!-- Deposit Article -->
                    <a href="{{ url('deposit-article') }}" @click="pubDropdownOpen = false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-sky-600 dark:hover:text-sky-400 rounded-xl transition-colors" wire:navigate>
                        <i class="fas fa-upload text-zinc-400 w-5 text-center shrink-0"></i>
                        <span>Deposit Article</span>
                    </a>
                </div>
            </div>

            <!-- 2. Mentorship Dropdown -->
            <div 
                class="relative py-2" 
                @mouseenter="mentorDropdownOpen = true" 
                @mouseleave="mentorDropdownOpen = false"
                @click.outside="mentorDropdownOpen = false"
            >
                <button 
                    @click="mentorDropdownOpen = !mentorDropdownOpen"
                    class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors cursor-pointer"
                    :class="mentorDropdownOpen || {{ request()->is('mentorship*') ? 'true' : 'false' }} ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : ''"
                >
                    <i class="fas fa-user-tie text-[20px] leading-6"></i>
                    <span class="flex items-center gap-0.5 font-medium">
                        Mentorship
                        <span class="text-[9px] transition-transform inline-block ml-0.5" :class="mentorDropdownOpen ? 'rotate-180' : ''">▼</span>
                    </span>
                </button>

                <!-- Mentorship Menu -->
                <div 
                    x-show="mentorDropdownOpen" 
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute top-full left-0 w-52 bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700/80 rounded-2xl shadow-xl p-2 z-50 origin-top-left"
                    style="display: none;"
                >
                    <a href="{{ url('mentorship') }}" @click="mentorDropdownOpen = false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-sky-600 dark:hover:text-sky-400 rounded-xl transition-colors" wire:navigate>
                        <i class="fas fa-eye text-zinc-400 w-5 text-center shrink-0"></i>
                        <span>Overview</span>
                    </a>
                    <a href="{{ url('mentor-sessions') }}" @click="mentorDropdownOpen = false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-sky-600 dark:hover:text-sky-400 rounded-xl transition-colors" wire:navigate>
                        <i class="fas fa-calendar-alt text-zinc-400 w-5 text-center shrink-0"></i>
                        <span>Sessions</span>
                    </a>
                </div>
            </div>

            <!-- 3. Q&A -->
            <a href="{{ url('questions') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('questions*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <i class="fas fa-question-circle text-[20px] leading-6"></i>
                <span>Q&A</span>
            </a>

            <!-- 4. Institutions -->
            <a href="{{ url('institutions') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('institutions*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <i class="fas fa-university text-[20px] leading-6"></i>
                <span>Institutions</span>
            </a>

            <!-- 5. Scholars / Graduate Cap -->
            <a href="{{ url('network') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('network*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                @if(auth()->check())
                    <i class="fas fa-users text-[20px] leading-6"></i>
                @else
                    <i class="fas fa-user-graduate text-[20px] leading-6"></i>
                @endif
                <span>{{ auth()->check() ? 'Network' : 'Scholars' }}</span>
            </a>

            <!-- 6. Journals -->
            <a href="{{ url('journals') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('journals*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <i class="fas fa-book text-[20px] leading-6"></i>
                <span>Journals</span>
            </a>

            <!-- 7. Reviewer / Enroll as Reviewer -->
            @auth
                @php
                    $navAuthUser = auth()->user();
                    $isNavUserVerified = $navAuthUser && ($navAuthUser->verified == '1' || $navAuthUser->verified === 1 || $navAuthUser->verified === 'Active') && !empty($navAuthUser->email_verified_at);
                    $pendingReviewsCount = $pendingReviewsCount ?? 0;
                @endphp
                @if ($isNavUserVerified)
                    @if (is_null($navAuthUser->reviewer_status) || $navAuthUser->reviewer_status === '')
                        <a href="{{ url('reviewer') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-semibold text-zinc-900 bg-[#F3C500] hover:bg-[#e0b600] rounded-lg transition-colors shadow-xs" wire:navigate>
                            <i class="fas fa-user-check text-[22px] text-zinc-900"></i>
                            <span class="whitespace-nowrap">Enroll as Reviewer</span>
                        </a>
                    @elseif ((string) $navAuthUser->reviewer_status === '0')
                        <a href="{{ url('/reviewer/profile') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('reviewer*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                            <i class="fas fa-clipboard-check text-[22px]"></i>
                            <span class="whitespace-nowrap">Reviewer Panel</span>
                        </a>
                    @elseif ((string) $navAuthUser->reviewer_status === '1')
                        <a href="{{ url('reviewer/profile') }}" class="relative flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('reviewer*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                            <i class="fas fa-clipboard-check text-[22px]"></i>
                            <span class="whitespace-nowrap">Reviewer Panel</span>
                            @if (!empty($pendingReviewsCount) && $pendingReviewsCount > 0)
                                <span class="absolute top-1 right-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full leading-none">{{ $pendingReviewsCount }}</span>
                            @endif
                        </a>
                    @endif
                @endif
            @endauth
        </nav>

        <!-- Right Section: User Profile or Sign Up Button -->
        <div class="flex items-center gap-2">
            
            <!-- Mobile Search Toggle Button -->
            <button 
                @click="searchOpen = !searchOpen" 
                class="md:hidden p-2 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors"
                aria-label="Search"
            >
                <i class="fas fa-search text-base"></i>
            </button>

            @auth
                <!-- Logged In User Dropdown with Hover & Click -->
                <div 
                    class="relative py-2" 
                    @mouseenter="userDropdownOpen = true" 
                    @mouseleave="userDropdownOpen = false"
                    @click.outside="userDropdownOpen = false"
                >
                    <button 
                        @click="userDropdownOpen = !userDropdownOpen"
                        class="flex items-center gap-2.5 p-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl transition-colors cursor-pointer"
                    >
                        <img 
                            src="{{ auth()->user()->avatar }}" 
                            alt="{{ auth()->user()->name }}" 
                            class="w-8 h-8 rounded-full object-cover ring-2 ring-sky-500/40"
                        />
                        <div class="hidden sm:flex items-center gap-1 text-left">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 truncate max-w-[90px]">
                                    {{ auth()->user()->name }}
                                </span>
                                <span class="text-[10px] text-zinc-400 leading-none">Scholar</span>
                            </div>
                            <span class="text-[9px] text-zinc-400 transition-transform inline-block ml-1" :class="userDropdownOpen ? 'rotate-180' : ''">▼</span>
                        </div>
                    </button>

                    <!-- User Menu Dropdown Panel -->
                    <div 
                        x-show="userDropdownOpen" 
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute top-full right-0 w-64 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-2xl py-2 z-50 divide-y divide-zinc-100 dark:divide-zinc-700 origin-top-right"
                        style="display: none;"
                    >
                        <!-- User Card -->
                        <div class="flex items-center gap-3 px-4 py-3 bg-gradient-to-br from-sky-50 to-indigo-50/30 dark:from-zinc-800 dark:to-zinc-800/80">
                            <img 
                                src="{{ auth()->user()->avatar }}" 
                                alt="{{ auth()->user()->name }}" 
                                class="w-10 h-10 rounded-full object-cover ring-2 ring-sky-500" 
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ auth()->user()->email }}</p>
                            </div>
                        </div>

                        <!-- Menu Links -->
                        <div class="py-1">
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" @click="userDropdownOpen = false" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/40 transition-colors" wire:navigate>
                                    <i class="fas fa-user-shield text-purple-500 text-sm w-4 text-center shrink-0"></i>
                                    <span>Admin Panel</span>
                                </a>
                            @endif
                            <a href="{{ route('dashboard') }}" @click="userDropdownOpen = false" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 hover:text-sky-600 transition-colors" wire:navigate>
                                <i class="fas fa-tachometer-alt text-zinc-400 text-sm w-4 text-center shrink-0"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="{{ route('settings.profile') }}" @click="userDropdownOpen = false" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 hover:text-sky-600 transition-colors" wire:navigate>
                                <i class="fas fa-user text-zinc-400 text-sm w-4 text-center shrink-0"></i>
                                <span>Settings & Profile</span>
                            </a>
                        </div>

                        <!-- Logout -->
                        <div class="py-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors text-left cursor-pointer">
                                    <i class="fas fa-sign-out-alt text-red-500 text-sm w-4 text-center shrink-0"></i>
                                    <span>Log Out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <!-- Guest Button (Login / Sign Up) -->
                <a 
                    href="{{ route('login') }}" 
                    class="inline-flex items-center justify-center gap-2 px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm font-semibold text-white bg-gradient-to-br from-[#198BEA] to-[#1565c0] hover:from-[#1579cc] hover:to-[#0f4f99] rounded-xl shadow-md shadow-[#198BEA]/30 hover:shadow-lg hover:shadow-[#198BEA]/40 hover:-translate-y-0.5 active:translate-y-0 transition-all"
                    wire:navigate
                >
                    <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                    <span>Login/Sign up</span>
                </a>
            @endauth

            <!-- Mobile Hamburger Button -->
            <button 
                @click="mobileMenuOpen = true" 
                class="lg:hidden p-2 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors"
                aria-label="Open Menu"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- Mobile Search Overlay -->
    <div 
        x-show="searchOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="md:hidden p-4 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800"
        style="display: none;"
    >
        <div class="flex items-center gap-2">
            <input 
                type="text" 
                placeholder="Search researchers, publications..." 
                class="flex-1 px-4 py-2 text-sm bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500"
            />
            <button @click="searchOpen = false" class="p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg">
                <i class="fas fa-times text-base"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation Drawer Overlay -->
    <div 
        x-show="mobileMenuOpen" 
        class="fixed inset-0 z-50 lg:hidden"
        style="display: none;"
    >
        <!-- Backdrop -->
        <div 
            x-show="mobileMenuOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false" 
            class="fixed inset-0 bg-black/50 backdrop-blur-xs"
        ></div>

        <!-- Slide-over panel -->
        <div 
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 right-0 max-w-xs w-full bg-white dark:bg-zinc-900 shadow-2xl p-6 flex flex-col justify-between overflow-y-auto"
        >
            <div class="space-y-6">
                <!-- Mobile Drawer Header -->
                <div class="flex items-center justify-between pb-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <x-app-logo class="h-7 w-auto" />
                        <span class="font-bold text-sm text-zinc-900 dark:text-white">ScholarHub</span>
                    </div>
                    <button @click="mobileMenuOpen = false" class="p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Mobile Nav Links -->
                <nav class="space-y-1">
                    <!-- Publications Accordion -->
                    <div>
                        <button 
                            @click="mobilePubOpen = !mobilePubOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl"
                        >
                            <span class="flex items-center gap-3">
                                <i class="fas fa-file-alt text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                                Publications
                            </span>
                            <span class="text-[10px] text-zinc-400 transition-transform inline-block" :class="mobilePubOpen ? 'rotate-180' : ''">▼</span>
                        </button>
                        <div x-show="mobilePubOpen" class="pl-10 pr-2 py-1 space-y-1 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg my-1">
                            <a href="{{ url('articles') }}" class="block py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600">Article List</a>
                            <a href="{{ url('deposit-article') }}" class="block py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600">Deposit Article</a>
                        </div>
                    </div>

                    <!-- Mentorship Accordion -->
                    <div>
                        <button 
                            @click="mobileMentorOpen = !mobileMentorOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl"
                        >
                            <span class="flex items-center gap-3">
                                <i class="fas fa-user-tie text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                                Mentorship
                            </span>
                            <span class="text-[10px] text-zinc-400 transition-transform inline-block" :class="mobileMentorOpen ? 'rotate-180' : ''">▼</span>
                        </button>
                        <div x-show="mobileMentorOpen" class="pl-10 pr-2 py-1 space-y-1 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg my-1">
                            <a href="{{ url('mentorship') }}" class="block py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600">Overview</a>
                            <a href="{{ url('mentor-sessions') }}" class="block py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600">Sessions</a>
                        </div>
                    </div>

                    <a href="{{ url('questions') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        <i class="fas fa-question-circle text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                        <span>Q&A</span>
                    </a>

                    <a href="{{ url('institutions') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        <i class="fas fa-university text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                        <span>Institutions</span>
                    </a>

                    <a href="{{ url('network') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        @if(auth()->check())
                            <i class="fas fa-users text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                        @else
                            <i class="fas fa-user-graduate text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                        @endif
                        <span>{{ auth()->check() ? 'Network' : 'Scholars' }}</span>
                    </a>

                    <a href="{{ url('journals') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        <i class="fas fa-book text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                        <span>Journals</span>
                    </a>

                    @auth
                        @php
                            $drawerAuthUser = auth()->user();
                            $isDrawerUserVerified = $drawerAuthUser && ($drawerAuthUser->verified == '1' || $drawerAuthUser->verified === 1 || $drawerAuthUser->verified === 'Active') && !empty($drawerAuthUser->email_verified_at);
                        @endphp
                        @if ($isDrawerUserVerified)
                            @if (is_null($drawerAuthUser->reviewer_status) || $drawerAuthUser->reviewer_status === '')
                                <a href="{{ url('reviewer') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-900 bg-[#F3C500] hover:bg-[#e0b600] rounded-xl transition-colors" wire:navigate>
                                    <i class="fas fa-user-check text-lg w-6 text-center text-zinc-900 shrink-0"></i>
                                    <span>Enroll as Reviewer</span>
                                </a>
                            @elseif ((string) $drawerAuthUser->reviewer_status === '0')
                                <a href="{{ url('/reviewer/profile') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl {{ request()->is('reviewer*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                                    <i class="fas fa-clipboard-check text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                                    <span>Reviewer Panel</span>
                                </a>
                            @elseif ((string) $drawerAuthUser->reviewer_status === '1')
                                <a href="{{ url('reviewer/profile') }}" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl {{ request()->is('reviewer*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                                    <span class="flex items-center gap-3">
                                        <i class="fas fa-clipboard-check text-lg w-6 text-center text-zinc-500 shrink-0"></i>
                                        <span>Reviewer Panel</span>
                                    </span>
                                    @if (!empty($pendingReviewsCount) && $pendingReviewsCount > 0)
                                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-bold text-white bg-red-500 rounded-full">{{ $pendingReviewsCount }}</span>
                                    @endif
                                </a>
                            @endif
                        @endif
                    @endauth
                </nav>
            </div>

            <!-- Mobile Drawer Bottom Auth -->
            <div class="pt-6 border-t border-zinc-200 dark:border-zinc-800">
                @auth
                    <div class="flex items-center gap-3 mb-4">
                        <img 
                            src="{{ auth()->user()->avatar }}" 
                            alt="{{ auth()->user()->name }}" 
                            class="w-10 h-10 rounded-full object-cover ring-2 ring-sky-500" 
                        />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-zinc-500 truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center justify-center gap-2 w-full py-2.5 px-4 mb-3 text-xs font-semibold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 rounded-xl text-center">
                            <i class="fas fa-user-shield"></i>
                            <span>Admin Panel</span>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-semibold text-red-600 bg-red-50 dark:bg-red-950/40 rounded-xl text-center">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Log Out</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="flex items-center justify-center gap-2 w-full py-2.5 px-4 text-sm font-semibold text-white bg-gradient-to-br from-[#198BEA] to-[#1565c0] hover:from-[#1579cc] hover:to-[#0f4f99] rounded-xl text-center shadow-md shadow-[#198BEA]/30">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        <span>Login/Sign up</span>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</div>
