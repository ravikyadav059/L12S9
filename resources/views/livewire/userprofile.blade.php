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

                {{-- Section 6: Projects (Independent Child Volt Component) --}}
                <livewire:profile.projects 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-projects-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 7: Conferences & Seminars (Independent Child Volt Component) --}}
                <livewire:profile.seminars 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-seminars-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 8: Certificates (Independent Child Volt Component) --}}
                <livewire:profile.certificates 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-certificates-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 9: Awards & Achievements (Independent Child Volt Component) --}}
                <livewire:profile.awards 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-awards-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 10: Thesis Guided (Independent Child Volt Component) --}}
                <livewire:profile.thesis 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-thesis-'.($user ? $user->_id : 'guest')"
                />

                <livewire:profile.memberships 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-memberships-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 12: Patents (Independent Child Volt Component) --}}
                <livewire:profile.patents 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-patents-'.($user ? $user->_id : 'guest')"
                />

                {{-- Section 13: Invited Positions (Independent Child Volt Component) --}}
                <livewire:profile.invited-positions 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-invited-positions-'.($user ? $user->_id : 'guest')" 
                />

                {{-- Section 14: Role in Research Journals (Independent Child Volt Component) --}}
                <livewire:profile.journal-roles 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'profile-journal-roles-'.($user ? $user->_id : 'guest')" 
                />

                {{-- Publications (Independent Child Volt Component) --}}
                <livewire:profile.publications 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :key="'publications-'.($user ? $user->_id : 'guest')" 
                />
            </main>

            {{-- Sidebar Column (approx 30%) --}}
            <x-profile.sidebar 
                :user="$user" 
                :is-own-profile="$isOwnProfile" 
            />

        </div>
    </div>
</div>
