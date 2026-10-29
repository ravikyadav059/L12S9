@props([
    'stats' => [],
])

@php
    $publicationsCount = $stats['publications'] ?? 2;
    $followersCount = $stats['followers'] ?? 9;
    $followingCount = $stats['following'] ?? 14;
    $questionsCount = $stats['questions'] ?? 10;
@endphp

{{-- 
    Statistics Section - Exactly matching Design Page (.NewProfilestats-section):
    - margin: -28px auto 16px (-mt-7 mb-4 mx-auto)
    - max-width: 1220px (max-w-[1220px])
    - padding: 0 16px (px-4)
--}}
<section class="max-w-[1220px] mx-auto px-4 -mt-7 mb-4 relative z-10">
    {{-- .NewProfilestats-NewProfilecontainer: bg white, border-radius 16px, shadow 0 20px 60px rgba(0,0,0,0.12), padding 14px 18px, border 1px solid #e5e7eb --}}
    <div class="bg-white dark:bg-[#111c26] rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.12)] border border-[#e5e7eb] dark:border-zinc-800 py-3.5 px-4 sm:px-[18px]">
        {{-- .stats-grid: grid, repeat(4, 1fr), gap 16px (mobile: repeat(2, 1fr), gap 10px) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4">
            
            {{-- Stat 1: Publications --}}
            <a 
                href="#publications" 
                class="group block text-center py-2.5 px-2 rounded-xl bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 hover:bg-brand-50 dark:hover:bg-brand/10 hover:border-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-300 ease-in-out cursor-pointer no-underline"
            >
                <span class="block text-[26px] font-extrabold text-brand leading-none mb-0.5 tracking-[-1px]">
                    {{ $publicationsCount }}
                </span>
                <span class="block text-[13px] font-semibold text-[#111827] dark:text-zinc-200 uppercase tracking-[0.5px]">
                    Publications
                </span>
            </a>

            {{-- Stat 2: Followers --}}
            <a 
                href="#followers" 
                class="group block text-center py-2.5 px-2 rounded-xl bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 hover:bg-brand-50 dark:hover:bg-brand/10 hover:border-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-300 ease-in-out cursor-pointer no-underline"
            >
                <span class="block text-[26px] font-extrabold text-brand leading-none mb-0.5 tracking-[-1px]">
                    {{ $followersCount }}
                </span>
                <span class="block text-[13px] font-semibold text-[#111827] dark:text-zinc-200 uppercase tracking-[0.5px]">
                    Followers
                </span>
            </a>

            {{-- Stat 3: Following --}}
            <a 
                href="#following" 
                class="group block text-center py-2.5 px-2 rounded-xl bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 hover:bg-brand-50 dark:hover:bg-brand/10 hover:border-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-300 ease-in-out cursor-pointer no-underline"
            >
                <span class="block text-[26px] font-extrabold text-brand leading-none mb-0.5 tracking-[-1px]">
                    {{ $followingCount }}
                </span>
                <span class="block text-[13px] font-semibold text-[#111827] dark:text-zinc-200 uppercase tracking-[0.5px]">
                    Following
                </span>
            </a>

            {{-- Stat 4: Questions --}}
            <a 
                href="#questions" 
                class="group block text-center py-2.5 px-2 rounded-xl bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 hover:bg-brand-50 dark:hover:bg-brand/10 hover:border-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-300 ease-in-out cursor-pointer no-underline"
            >
                <span class="block text-[26px] font-extrabold text-brand leading-none mb-0.5 tracking-[-1px]">
                    {{ $questionsCount }}
                </span>
                <span class="block text-[13px] font-semibold text-[#111827] dark:text-zinc-200 uppercase tracking-[0.5px]">
                    Questions
                </span>
            </a>

        </div>
    </div>
</section>
