{{-- resources/views/livewire/admin/users/partials/header.blade.php --}}
<!-- PAGE BREADCRUMB, TITLE & TOP ACTIONS -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
            <a href="{{ route('admin.users.index') }}" wire:navigate class="hover:text-brand transition flex items-center gap-1">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Back to Users</span>
            </a>
            <span>/</span>
            <span class="text-zinc-700 dark:text-zinc-300 font-medium">Edit User</span>
        </div>
        <div class="flex items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white">
                Edit User Profile
            </h1>
            <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-brand/10 text-brand border border-brand/20">
                {{ $profileId }}
            </span>
        </div>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
            Update user credentials, profile attributes, and verification status.
        </p>
    </div>

    <div class="flex items-center gap-2">
        @if(! empty($user->slug))
            <a 
                href="{{ route('userscholar', ['slug' => $user->slug]) }}" 
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span>View Public Profile</span>
            </a>
        @endif
    </div>
</div>

<!-- GLOBAL FLASH MESSAGES -->
@if (session()->has('message'))
    <div class="px-4 py-3 text-xs font-semibold rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between shadow-xs transition">
        <div class="flex items-center gap-2">
            <svg class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('message') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-xs cursor-pointer p-1">✕</button>
    </div>
@endif

@if (session()->has('photo_error'))
    <div class="px-4 py-3 text-xs font-semibold rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex items-center justify-between shadow-xs transition">
        <div class="flex items-center gap-2">
            <svg class="size-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span>{{ session('photo_error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer p-1">✕</button>
    </div>
@endif
