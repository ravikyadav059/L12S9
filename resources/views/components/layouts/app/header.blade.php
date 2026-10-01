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
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
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
                    <!-- Solid Document Icon -->
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v17A1.5 1.5 0 0 0 4.5 22h15a1.5 1.5 0 0 0 1.5-1.5V7.5L14.5 2H4.5ZM14 3.5V8h4.5L14 3.5ZM6 11.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 11.5Zm0 3a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 14.5Zm0 3a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                    </svg>
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
                        <!-- Solid Document Badge Icon -->
                        <svg class="w-5 h-5 text-zinc-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.5 3.75a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V6.75a3 3 0 0 0-3-3h-15ZM6 8.25a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 8.25Zm0 3.75a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75Zm0 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                        </svg>
                        <span>Article List</span>
                    </a>

                    <!-- Deposit Article -->
                    <a href="{{ url('deposit-article') }}" @click="pubDropdownOpen = false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-sky-600 dark:hover:text-sky-400 rounded-xl transition-colors" wire:navigate>
                        <!-- Upload Outline Icon -->
                        <svg class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16.5v1.75A2.75 2.75 0 0 0 6.75 21h10.5A2.75 2.75 0 0 0 20 18.25V16.5m-8-12v11m0-11l-3.5 3.5M12 4.5l3.5 3.5"/>
                        </svg>
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
                    <!-- Solid Person with Tie Icon -->
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="6" r="3.5"/>
                        <path d="M12 11c-3.86 0-7 2.14-7 5v4h14v-4c0-2.86-3.14-5-7-5zm1.5 5.5l-.5 3.5h-2l-.5-3.5 1.5-1.5 1.5 1.5z"/>
                    </svg>
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
                        <svg class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Overview</span>
                    </a>
                    <a href="{{ url('mentor-sessions') }}" @click="mentorDropdownOpen = false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-sky-600 dark:hover:text-sky-400 rounded-xl transition-colors" wire:navigate>
                        <svg class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Sessions</span>
                    </a>
                </div>
            </div>

            <!-- 3. Q&A -->
            <a href="{{ url('questions') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('questions*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <!-- Solid Question Circle -->
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-.53 14.03a.75.75 0 0 0 1.06 0l.008-.008a.75.75 0 0 0-1.06-1.06l-.008.008a.75.75 0 0 0 0 1.06Zm1.28-4.502a.75.75 0 0 0-.75-.75c-.828 0-1.5-.672-1.5-1.5 0-.828.672-1.5 1.5-1.5.828 0 1.5.672 1.5 1.5 0 .227-.04.444-.114.646-.29.791-.886 1.404-1.386 2.054V13.5a.75.75 0 0 0 1.5 0v-.354c.414-.54.912-1.08 1.196-1.785.197-.488.304-1.02.304-1.583 0-1.657-1.343-3-3-3s-3 1.343-3 3a2.25 2.25 0 0 0 2.25 2.25Z" clip-rule="evenodd"/>
                </svg>
                <span>Q&A</span>
            </a>

            <!-- 4. Institutions -->
            <a href="{{ url('institutions') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('institutions*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <!-- Solid University/Building -->
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2L2 7v2h20V7L12 2zM4 11v8h3v-8H4zm6 0v8h4v-8h-4zm7 0v8h3v-8h-3zM2 21v2h20v-2H2z"/>
                </svg>
                <span>Institutions</span>
            </a>

            <!-- 5. Scholars / Graduate Cap -->
            <a href="{{ url('network') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('network*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <!-- Solid Scholar with Cap Icon -->
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2 1 7l11 5 9-4.09V15h2V7L12 2z"/>
                    <circle cx="12" cy="11.5" r="3"/>
                    <path d="M6 19.5c0-2 2.69-3.5 6-3.5s6 1.5 6 3.5V21H6v-1.5z"/>
                </svg>
                <span>{{ auth()->check() ? 'Network' : 'Scholars' }}</span>
            </a>

            <!-- 6. Journals -->
            <a href="{{ url('journals') }}" class="flex flex-col items-center gap-1 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors {{ request()->is('journals*') ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40' : '' }}" wire:navigate>
                <!-- Solid Book Icon -->
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.5 3.75A2.25 2.25 0 0 0 2.25 6v12.75a2.25 2.25 0 0 0 2.25 2.25h14.25a.75.75 0 0 0 .75-.75V6a2.25 2.25 0 0 0-2.25-2.25H4.5ZM6 7.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 7.5Zm0 3.75a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75Zm0 3.75a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                </svg>
                <span>Journals</span>
            </a>
        </nav>

        <!-- Right Section: User Profile or Sign Up Button -->
        <div class="flex items-center gap-2">
            
            <!-- Mobile Search Toggle Button -->
            <button 
                @click="searchOpen = !searchOpen" 
                class="md:hidden p-2 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors"
                aria-label="Search"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z" clip-rule="evenodd"/>
                </svg>
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
                            src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=0284c7&color=fff' }}" 
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
                                src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=0284c7&color=fff' }}" 
                                class="w-10 h-10 rounded-full object-cover ring-2 ring-sky-500" 
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ auth()->user()->email }}</p>
                            </div>
                        </div>

                        <!-- Menu Links -->
                        <div class="py-1">
                            <a href="{{ route('dashboard') }}" @click="userDropdownOpen = false" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 hover:text-sky-600 transition-colors" wire:navigate>
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                Dashboard
                            </a>
                            <a href="{{ route('settings.profile') }}" @click="userDropdownOpen = false" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 hover:text-sky-600 transition-colors" wire:navigate>
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Settings & Profile
                            </a>
                        </div>

                        <!-- Logout -->
                        <div class="py-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors text-left cursor-pointer">
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <!-- Guest Button (Login / Sign Up) matching Image 1 -->
                <a 
                    href="{{ route('login') }}" 
                    class="inline-flex items-center justify-center gap-1.5 px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm font-semibold text-white bg-gradient-to-br from-[#198BEA] to-[#1565c0] hover:from-[#1579cc] hover:to-[#0f4f99] rounded-xl shadow-md shadow-[#198BEA]/30 hover:shadow-lg hover:shadow-[#198BEA]/40 hover:-translate-y-0.5 active:translate-y-0 transition-all"
                    wire:navigate
                >
                    <span>Login/Sign up</span>
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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
                                <svg class="w-6 h-6 text-zinc-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v17A1.5 1.5 0 0 0 4.5 22h15a1.5 1.5 0 0 0 1.5-1.5V7.5L14.5 2H4.5ZM14 3.5V8h4.5L14 3.5ZM6 11.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 11.5Zm0 3a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 14.5Zm0 3a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                                </svg>
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
                                <svg class="w-6 h-6 text-zinc-500" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="12" cy="6" r="3.5"/>
                                    <path d="M12 11c-3.86 0-7 2.14-7 5v4h14v-4c0-2.86-3.14-5-7-5zm1.5 5.5l-.5 3.5h-2l-.5-3.5 1.5-1.5 1.5 1.5z"/>
                                </svg>
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
                        <svg class="w-6 h-6 text-zinc-500" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-.53 14.03a.75.75 0 0 0 1.06 0l.008-.008a.75.75 0 0 0-1.06-1.06l-.008.008a.75.75 0 0 0 0 1.06Zm1.28-4.502a.75.75 0 0 0-.75-.75c-.828 0-1.5-.672-1.5-1.5 0-.828.672-1.5 1.5-1.5.828 0 1.5.672 1.5 1.5 0 .227-.04.444-.114.646-.29.791-.886 1.404-1.386 2.054V13.5a.75.75 0 0 0 1.5 0v-.354c.414-.54.912-1.08 1.196-1.785.197-.488.304-1.02.304-1.583 0-1.657-1.343-3-3-3s-3 1.343-3 3a2.25 2.25 0 0 0 2.25 2.25Z" clip-rule="evenodd"/>
                        </svg>
                        Q&A
                    </a>

                    <a href="{{ url('institutions') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        <svg class="w-6 h-6 text-zinc-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 7v2h20V7L12 2zM4 11v8h3v-8H4zm6 0v8h4v-8h-4zm7 0v8h3v-8h-3zM2 21v2h20v-2H2z"/>
                        </svg>
                        Institutions
                    </a>

                    <a href="{{ url('network') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        <svg class="w-6 h-6 text-zinc-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2 1 7l11 5 9-4.09V15h2V7L12 2z"/>
                            <circle cx="12" cy="11.5" r="3"/>
                            <path d="M6 19.5c0-2 2.69-3.5 6-3.5s6 1.5 6 3.5V21H6v-1.5z"/>
                        </svg>
                        {{ auth()->check() ? 'Network' : 'Scholars' }}
                    </a>

                    <a href="{{ url('journals') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl" wire:navigate>
                        <svg class="w-6 h-6 text-zinc-500" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.5 3.75A2.25 2.25 0 0 0 2.25 6v12.75a2.25 2.25 0 0 0 2.25 2.25h14.25a.75.75 0 0 0 .75-.75V6a2.25 2.25 0 0 0-2.25-2.25H4.5ZM6 7.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 7.5Zm0 3.75a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75Zm0 3.75a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                        </svg>
                        Journals
                    </a>
                </nav>
            </div>

            <!-- Mobile Drawer Bottom Auth -->
            <div class="pt-6 border-t border-zinc-200 dark:border-zinc-800">
                @auth
                    <div class="flex items-center gap-3 mb-4">
                        <img 
                            src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=0284c7&color=fff' }}" 
                            class="w-10 h-10 rounded-full object-cover ring-2 ring-sky-500" 
                        />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-zinc-500 truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full py-2.5 px-4 text-xs font-semibold text-red-600 bg-red-50 dark:bg-red-950/40 rounded-xl text-center">
                            Log Out
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="flex items-center justify-center gap-1.5 w-full py-2.5 px-4 text-sm font-semibold text-white bg-gradient-to-br from-[#198BEA] to-[#1565c0] hover:from-[#1579cc] hover:to-[#0f4f99] rounded-xl text-center shadow-md shadow-[#198BEA]/30">
                        <span>Login/Sign up</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</div>
