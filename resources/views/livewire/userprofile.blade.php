<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';
    public ?User $user = null;
    public bool $isOwnProfile = true;

    // Preview dummy data for UI step
    public array $dummyHero = [
        'title' => 'Director',
        'organization' => 'Janvi Wave LLP',
        'role' => 'Research Journal Publisher',
        'top_experience' => 'Janvi Wave LLP',
        'top_education' => 'Sequence Research & Development Private Limited',
        'completeness' => 100,
    ];

    public array $dummyStats = [
        'publications' => 2,
        'followers' => 9,
        'following' => 14,
        'questions' => 10,
    ];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->user = User::where('slug', $slug)
            ->with('reviewerProfile')
            ->first();

        // Own Profile detection
        if (auth()->check() && $this->user) {
            $this->isOwnProfile = (string) auth()->id() === (string) $this->user->_id;
        } else {
            // Default to true for UI design verification preview
            $this->isOwnProfile = true;
        }
    }
}; ?>

<div class="min-h-screen bg-gray-50/60 dark:bg-zinc-900 pb-16">
    {{-- 1. Full-Width Sky-Blue Hero Banner --}}
    <x-profile.hero 
        :user="$user" 
        :is-own-profile="$isOwnProfile" 
        :dummy-data="$dummyHero" 
    />

    {{-- 2. Floating Statistics Counter Bar (overlapping bottom edge of hero) --}}
    <x-profile.stats-bar 
        :stats="$dummyStats" 
    />

    {{-- 3. Sticky Horizontal Navigation --}}
    <x-profile.section-nav />

    {{-- 4. Main 2-Column Content Area (Matching Question & Answer Page Layout) --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 pb-12">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            {{-- Primary Content Column (Matches Question Page Left Feed Column) --}}
            <main class="flex-1 min-w-0 w-full space-y-6">
                {{-- Section 1: About (Independent Child Volt Component) --}}
                <livewire:profile.about 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-about-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 2: Experience (Independent Child Volt Component) --}}
                <livewire:profile.experience 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-experience-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 3: Education (Independent Child Volt Component) --}}
                <livewire:profile.education 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-education-'.($user ? $user->_id : 'guest')"
                />

                <div id="publications" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">📚</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Publications</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 5</p>
                </div>

                <div id="projects" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">🚀</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Projects</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 6</p>
                </div>

                <div id="conferences" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">🎤</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Conferences</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 7</p>
                </div>

                <div id="certificates" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">📜</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Certificates</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 8</p>
                </div>

                <div id="achievements" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">🏆</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Awards &amp; Achievements</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 9</p>
                </div>

                <div id="thesis" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">📑</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Thesis Guided</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 10</p>
                </div>

                <div id="memberships" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">👥</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Memberships</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 11</p>
                </div>

                <div id="patents" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">💡</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Patents</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 12</p>
                </div>

                <div id="invited-positions" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">🏛️</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Invited Position</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 13</p>
                </div>

                <div id="editorial-roles" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">📖</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Role in Research Journals</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 14</p>
                </div>
            </main>

            {{-- Sidebar Column (approx 30%) --}}
            <x-profile.sidebar 
                :user="$user" 
                :is-own-profile="$isOwnProfile" 
            />

        </div>
    </div>
</div>
