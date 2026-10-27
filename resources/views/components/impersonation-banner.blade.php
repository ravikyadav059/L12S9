@if(session()->has('impersonated_by') && auth()->check())
    @php
        $impersonatedUser = auth()->user();
        $impersonatedName = $impersonatedUser->fullname 
            ?: ($impersonatedUser->name 
            ?: trim(($impersonatedUser->first_name ?? '') . ' ' . ($impersonatedUser->last_name ?? '')));
        
        if (empty($impersonatedName)) {
            $impersonatedName = $impersonatedUser->email ?? 'User';
        }
    @endphp
    <div class="w-full bg-gradient-to-r from-amber-500 via-amber-600 to-amber-500 text-white py-2 px-4 shadow-md z-[100] border-b border-amber-600/40">
        <div class="max-w-7xl mx-auto flex items-center justify-between flex-wrap gap-2 text-xs sm:text-sm">
            <div class="flex items-center gap-2">
                <svg class="size-4 shrink-0 text-amber-100 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="tracking-wide">
                    You are currently browsing as <strong class="font-bold underline underline-offset-2">{{ $impersonatedName }}</strong>
                </span>
            </div>
            <div>
                <a 
                    href="{{ route('impersonate.leave') }}" 
                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-white text-amber-900 hover:bg-amber-50 font-bold text-xs rounded-lg shadow-xs transition hover:scale-[1.02] active:scale-[0.98]"
                >
                    <span>Click here to return to Admin</span>
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
@endif
