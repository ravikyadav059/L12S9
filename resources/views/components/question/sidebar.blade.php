@props([
    'totalQuestionsCount' => 0,
    'totalAnswersCount' => 0,
    'totalUsersCount' => 0,
    'resolvedPercentage' => '92%',
    'userSkills' => [],
    'commonTags' => [],
    'activeContributors' => [],
    'popularQuestions' => [],
    'followingUsers' => [],
    'selectedTag' => '',
])

<!-- RIGHT SIDEBAR COLUMN (MAX 320px ON DESKTOP) -->
<aside class="w-full lg:w-[320px] lg:max-w-[320px] shrink-0 space-y-5">
    
    <!-- 1. Community Stats Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Community Stats
        </h3>
        <div class="grid grid-cols-2 gap-2.5 text-center">
            <div class="p-3 rounded-xl bg-[#eaf5ff] dark:bg-sky-950/40">
                <div class="text-lg font-extrabold text-[#198BEA]">{{ number_format($totalQuestionsCount) }}</div>
                <div class="text-[11px] font-medium text-sky-700 dark:text-sky-300">Questions</div>
            </div>
            <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/40">
                <div class="text-lg font-extrabold text-purple-600 dark:text-purple-400">{{ number_format($totalAnswersCount) }}</div>
                <div class="text-[11px] font-medium text-purple-700 dark:text-purple-300">Answers</div>
            </div>
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40">
                <div class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400">{{ number_format($totalUsersCount) }}</div>
                <div class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300">Users</div>
            </div>
            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40">
                <div class="text-lg font-extrabold text-amber-600 dark:text-amber-400">{{ $resolvedPercentage }}</div>
                <div class="text-[11px] font-medium text-amber-700 dark:text-amber-300">Resolved</div>
            </div>
        </div>
    </div>

    <!-- 2. Skills & Expertise Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                My Skills &amp; Expertise
            </h3>
            <button 
                wire:click="openSkillsModal"
                type="button"
                class="p-1 text-zinc-400 hover:text-[#198BEA] hover:bg-sky-50 dark:hover:bg-zinc-800 rounded-lg transition-colors cursor-pointer"
                title="Edit skills"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
        </div>

        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3 leading-relaxed">
            We use your skills to show relevant questions. You can customize them anytime.
        </p>

        @if(!empty($userSkills))
            <div class="flex flex-wrap gap-1.5 mb-3.5">
                @foreach($userSkills as $skill)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 rounded-lg text-xs font-semibold">
                        {{ $skill }}
                    </span>
                @endforeach
            </div>
        @else
            <div class="text-center py-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800 mb-3.5">
                <p class="text-xs text-zinc-400">No skills added yet</p>
            </div>
        @endif

        <button 
            wire:click="openSkillsModal"
            type="button"
            class="w-full flex items-center justify-center gap-1.5 py-2 border border-[#198BEA] text-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/50 rounded-xl text-xs font-bold transition-all cursor-pointer"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span>Add / Edit Skills</span>
        </button>
    </div>

    <!-- 3. Related Tags Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2 mb-3.5">
            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            Related Tags
        </h3>
        <div class="flex flex-wrap gap-1.5">
            @foreach($commonTags as $item)
                <button 
                    wire:click="filterByTag('{{ $item['name'] }}')"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800/80 hover:bg-[#eaf5ff] hover:text-[#198BEA] dark:hover:bg-sky-950/60 dark:hover:text-sky-300 text-zinc-700 dark:text-zinc-300 rounded-lg text-xs font-medium transition-all cursor-pointer {{ $selectedTag === $item['name'] ? 'ring-2 ring-[#198BEA] bg-[#eaf5ff] text-[#198BEA]' : '' }}"
                >
                    <span>{{ $item['name'] }}</span>
                    <span class="text-[10px] text-zinc-400">{{ $item['count'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <!-- 4. Top Contributors Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2 mb-3.5">
            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Most Active Scholars
        </h3>
        <div class="space-y-3">
            @foreach($activeContributors as $idx => $user)
                @php
                    $uName = $user->fullname ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'Scholar '.($idx+1);
                    $uAvatar = $user->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode($uName).'&background=198BEA&color=fff';
                    $isFollow = in_array((string)$user->id, $followingUsers, true);
                @endphp
                <div class="flex items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="relative shrink-0">
                            <img src="{{ $uAvatar }}" alt="{{ $uName }}" class="w-9 h-9 rounded-full object-cover" />
                            <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full text-[9px] font-bold flex items-center justify-center {{ $idx === 0 ? 'bg-amber-400 text-zinc-900' : ($idx === 1 ? 'bg-zinc-300 text-zinc-900' : 'bg-amber-700 text-white') }}">
                                {{ $idx + 1 }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-zinc-900 dark:text-white truncate">
                                {{ $uName }}
                            </div>
                            <div class="text-[10px] text-zinc-400">
                                {{ rand(50, 200) }} answers · {{ number_format(rand(1000, 5000)) }} pts
                            </div>
                        </div>
                    </div>
                    <button 
                        wire:click="toggleFollowUser('{{ (string)$user->id }}', '{{ $uName }}')"
                        type="button"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold transition-all cursor-pointer {{ $isFollow ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'text-[#198BEA] border border-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/50' }}"
                    >
                        {{ $isFollow ? 'Following' : 'Follow' }}
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 5. Popular Questions Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2 mb-3.5">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
            Most Asked Questions
        </h3>
        <div class="space-y-3 divide-y divide-zinc-100 dark:divide-zinc-800/80">
            @foreach($popularQuestions as $pop)
                <div class="pt-3 first:pt-0">
                    <a 
                        href="#{{ $pop->id }}"
                        class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors line-clamp-2 leading-snug"
                    >
                        {{ $pop->title }}
                    </a>
                    <div class="flex items-center gap-2 mt-1 text-[11px] text-zinc-400">
                        <span>{{ $pop->answer_count ?? 0 }} answers</span>
                        <span>·</span>
                        <span class="{{ $pop->is_closed ? 'text-emerald-600 font-semibold' : 'text-[#198BEA]' }}">
                            {{ $pop->is_closed ? 'Resolved' : 'Active' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 6. Quick Links Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Quick Links
        </h3>
        <div class="space-y-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">
            <a href="{{ route('articles.index') }}" class="flex items-center gap-2 p-2 rounded-lg hover:bg-sky-50 dark:hover:bg-zinc-800 hover:text-[#198BEA] transition-colors">
                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Browse Research Articles</span>
            </a>
            <a href="{{ route('journals.index') }}" class="flex items-center gap-2 p-2 rounded-lg hover:bg-sky-50 dark:hover:bg-zinc-800 hover:text-[#198BEA] transition-colors">
                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                <span>Peer-Reviewed Journals</span>
            </a>
            <a href="{{ route('institutions.index') }}" class="flex items-center gap-2 p-2 rounded-lg hover:bg-sky-50 dark:hover:bg-zinc-800 hover:text-[#198BEA] transition-colors">
                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Academic Institutions</span>
            </a>
            <a href="{{ route('articles.deposit') }}" class="flex items-center gap-2 p-2 rounded-lg hover:bg-sky-50 dark:hover:bg-zinc-800 hover:text-[#198BEA] transition-colors">
                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Deposit Your Paper Free</span>
            </a>
        </div>
    </div>

</aside>
