<?php

use App\Models\Publication;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Admin Dashboard')] class extends Component {
    public function with(): array
    {
        $totalPublications = Publication::count();
        $publishedPublications = Publication::where('status', 'published')->orWhere('option', 'published')->count();
        $pendingPublications = Publication::where('status', 'pending')->orWhere('option', 'preprint')->count();
        $totalUsers = User::count();

        $recentPublications = Publication::latest()->limit(5)->get();
        $recentUsers = User::latest()->limit(5)->get();

        return [
            'totalPublications' => $totalPublications,
            'publishedPublications' => $publishedPublications,
            'pendingPublications' => $pendingPublications,
            'totalUsers' => $totalUsers,
            'recentPublications' => $recentPublications,
            'recentUsers' => $recentUsers,
        ];
    }
}; ?>

<div class="space-y-8">
    <!-- PAGE HERO GREETING -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-dark-navy via-[#1f3647] to-[#122230] p-6 sm:p-8 text-white shadow-lg border border-zinc-800">
        <div class="relative z-10 max-w-2xl space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-brand/20 text-brand-50 border border-brand/30">
                <svg class="size-3.5 text-brand" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                </svg>
                Scholar Administration Control
            </span>
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight">
                Welcome back, {{ auth()->user()?->name ?? 'Administrator' }}
            </h2>
            <p class="text-xs sm:text-sm text-zinc-300 leading-relaxed">
                Monitor system performance, review incoming research publications, manage scholar access, and run database fixing tools.
            </p>
        </div>
        <div class="absolute -right-8 -bottom-8 opacity-10 pointer-events-none">
            <svg class="w-64 h-64 text-brand" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
            </svg>
        </div>
    </div>

    <!-- METRIC KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Publications -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl p-5 border border-zinc-200 dark:border-zinc-800/90 shadow-xs hover:border-brand/40 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Publications</p>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($totalPublications) }}</h3>
                </div>
                <div class="size-11 rounded-xl bg-brand-50 dark:bg-brand/10 text-brand flex items-center justify-center">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                <a href="{{ route('admin.publications.index') }}" class="text-brand hover:underline inline-flex items-center gap-1">
                    Manage publications &rarr;
                </a>
            </div>
        </div>

        <!-- Published Articles -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl p-5 border border-zinc-200 dark:border-zinc-800/90 shadow-xs hover:border-emerald-500/40 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Published Articles</p>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($publishedPublications) }}</h3>
                </div>
                <div class="size-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                <span>Verified in catalog</span>
            </div>
        </div>

        <!-- Pending / Preprints -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl p-5 border border-zinc-200 dark:border-zinc-800/90 shadow-xs hover:border-amber-500/40 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Pending & Preprints</p>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($pendingPublications) }}</h3>
                </div>
                <div class="size-11 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium">
                <span>Awaiting review / preprints</span>
            </div>
        </div>

        <!-- Total Users -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl p-5 border border-zinc-200 dark:border-zinc-800/90 shadow-xs hover:border-purple-500/40 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Scholars & Users</p>
                    <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($totalUsers) }}</h3>
                </div>
                <div class="size-11 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-xs text-purple-600 dark:text-purple-400 font-medium">
                <a href="{{ route('admin.users.index') }}" class="hover:underline inline-flex items-center gap-1">
                    Manage accounts &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS & DATABASE TOOLS -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Action 1: Publications Directory -->
        <a href="{{ route('admin.publications.index') }}" class="group block p-6 rounded-2xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 hover:border-brand transition shadow-xs">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-brand/10 text-brand flex items-center justify-center group-hover:bg-brand group-hover:text-white transition">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-brand transition">Manage Publications</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Filter, approve, edit, or reject submitted papers</p>
                </div>
            </div>
        </a>

        <!-- Action 2: User Directory -->
        <a href="{{ route('admin.users.index') }}" class="group block p-6 rounded-2xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 hover:border-brand transition shadow-xs">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-purple-600 transition">User Management</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">View scholars, update roles, and manage permissions</p>
                </div>
            </div>
        </a>

        <!-- Action 3: Database Fixing -->
        <a href="{{ route('admin.database-fixing.index') }}" class="group block p-6 rounded-2xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 hover:border-brand transition shadow-xs">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-emerald-600 transition">Database Fixing</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Standardize raw records for publication & users</p>
                </div>
            </div>
        </a>
    </div>

    <!-- RECENT ACTIVITY SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Articles -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Recent Publications</h3>
                    <span class="px-2 py-0.5 text-[11px] rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-semibold">{{ $recentPublications->count() }}</span>
                </div>
                <a href="{{ route('admin.publications.index') }}" class="text-xs font-semibold text-brand hover:underline">View All &rarr;</a>
            </div>

            <div class="space-y-3">
                @forelse($recentPublications as $pub)
                    <div class="flex items-center justify-between gap-3 p-3 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs font-bold text-zinc-900 dark:text-white truncate">
                                {{ $pub->title ?: 'Untitled Article' }}
                            </h4>
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                {{ $pub->journal_name ?: 'Unknown Journal' }} &bull; {{ $pub->publication_month_year ?: 'Date N/A' }}
                            </p>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 text-[10px] font-bold uppercase rounded-md {{ $pub->option === 'published' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">
                            {{ $pub->option ?? 'preprint' }}
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-500 py-6 text-center">No publications found.</p>
                @endforelse
            </div>
        </div>

        <!-- Recent Users -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Recent Users & Scholars</h3>
                    <span class="px-2 py-0.5 text-[11px] rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-semibold">{{ $recentUsers->count() }}</span>
                </div>
                <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-brand hover:underline">View All &rarr;</a>
            </div>

            <div class="space-y-3">
                @forelse($recentUsers as $usr)
                    <div class="flex items-center justify-between gap-3 p-3 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-8 rounded-full bg-brand/10 text-brand font-bold flex items-center justify-center text-xs shrink-0">
                                {{ $usr->initials() }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-xs font-bold text-zinc-900 dark:text-white truncate">{{ $usr->name }}</h4>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">{{ $usr->email }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 text-[10px] font-bold uppercase rounded-md {{ $usr->isAdmin() ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                            {{ $usr->role ?? ($usr->isAdmin() ? 'Admin' : 'Scholar') }}
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-500 py-6 text-center">No registered users yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
