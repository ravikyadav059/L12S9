@props([
    'user' => null,
    'isOwnProfile' => true,
])

@php
    $sections = [
        ['id' => 'about', 'label' => 'About', 'icon' => '👤'],
        ['id' => 'experience', 'label' => 'Experience', 'icon' => '💼'],
        ['id' => 'education', 'label' => 'Education', 'icon' => '🎓'],
        ['id' => 'publications', 'label' => 'Publications', 'icon' => '📚'],
        ['id' => 'projects', 'label' => 'Projects', 'icon' => '🚀'],
        ['id' => 'conferences', 'label' => 'Conferences', 'icon' => '🎤'],
        ['id' => 'certificates', 'label' => 'Certificates', 'icon' => '📜'],
        ['id' => 'achievements', 'label' => 'Achievements', 'icon' => '🏆'],
        ['id' => 'thesis', 'label' => 'Thesis Guided', 'icon' => '📑'],
        ['id' => 'memberships', 'label' => 'Memberships', 'icon' => '👥'],
        ['id' => 'patents', 'label' => 'Patents', 'icon' => '💡'],
        ['id' => 'invited-positions', 'label' => 'Invited Position', 'icon' => '🏛️'],
        ['id' => 'editorial-roles', 'label' => 'Research Journals', 'icon' => '📖'],
    ];
@endphp

<!-- RIGHT SIDEBAR COLUMN (EXACT MATCH WITH QUESTION SIDEBAR: 320px ON DESKTOP) -->
<aside class="w-full lg:w-[320px] lg:max-w-[320px] shrink-0 space-y-5">
    {{-- Quick Navigation Widget (Target of IntersectionObserver) --}}
    <div 
        id="sidebar-quick-nav" 
        class="bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm"
    >
        <h3 class="text-sm font-bold font-space text-zinc-900 dark:text-white flex items-center gap-2 mb-3 pb-2 border-b border-zinc-100 dark:border-zinc-800">
            <span>📋</span>
            <span>Quick Navigation</span>
        </h3>
        <ul class="space-y-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">
            @foreach($sections as $sec)
                <li>
                    <a 
                        href="#{{ $sec['id'] }}" 
                        class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800/70 hover:text-brand transition-colors"
                    >
                        <span>{{ $sec['icon'] }}</span>
                        <span>{{ $sec['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- People You May Know Widget --}}
    <div class="bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm">
        <h3 class="text-sm font-bold font-space text-zinc-900 dark:text-white flex items-center gap-2 mb-4 pb-2 border-b border-zinc-100 dark:border-zinc-800">
            <span>👥</span>
            <span>People You May Know</span>
        </h3>
        <div class="space-y-3.5">
            {{-- Sample Person 1 --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="size-10 rounded-full bg-brand/10 border border-brand/20 flex items-center justify-center text-xs font-bold text-brand shrink-0">
                        AN
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1">
                            <h4 class="text-xs font-bold text-zinc-900 dark:text-white truncate">Aashna Narula</h4>
                            <img src="{{ asset('images/verified-tick.png') }}" class="size-3.5 shrink-0" alt="Verified" />
                        </div>
                        <p class="text-[11px] text-zinc-500 truncate">CEO &amp; Founder</p>
                        <p class="text-[10px] text-zinc-400 flex items-center gap-1">
                            <span>👥</span> 5 followers
                        </p>
                    </div>
                </div>
                <button type="button" class="shrink-0 px-3.5 py-1 text-xs font-semibold rounded-full border border-brand text-brand hover:bg-brand hover:text-white transition-colors">
                    Follow
                </button>
            </div>

            {{-- Sample Person 2 --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="size-10 rounded-full bg-brand/10 border border-brand/20 flex items-center justify-center text-xs font-bold text-brand shrink-0">
                        RK
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1">
                            <h4 class="text-xs font-bold text-zinc-900 dark:text-white truncate">Dr. Rajesh Kumar</h4>
                            <img src="{{ asset('images/verified-tick.png') }}" class="size-3.5 shrink-0" alt="Verified" />
                        </div>
                        <p class="text-[11px] text-zinc-500 truncate">Professor • AI &amp; ML</p>
                        <p class="text-[10px] text-zinc-400 flex items-center gap-1">
                            <span>👥</span> 12 followers
                        </p>
                    </div>
                </div>
                <button type="button" class="shrink-0 px-3.5 py-1 text-xs font-semibold rounded-full border border-brand text-brand hover:bg-brand hover:text-white transition-colors">
                    Follow
                </button>
            </div>
        </div>
    </div>
</aside>
