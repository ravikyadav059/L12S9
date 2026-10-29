<?php

use App\Models\Designation;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\ReviewerProfile;
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

    /**
     * Get experiences for this user profile.
     * Scholar/user view ONLY shows records with status 1 or '1'.
     *
     * @return list<array<string, mixed>>
     */
    public function getExperiencesProperty(): array
    {
        $reviewerProfile = $this->user?->reviewerProfile ?? ($this->user ? ReviewerProfile::where('user_id', (string) $this->user->_id)->first() : null);
        $expIds = (array) ($reviewerProfile->experience ?? []);
        if (empty($expIds)) {
            return [];
        }

        // ONLY STATUS 1 or '1'
        $records = Experience::whereIn('_id', $expIds)
            ->whereIn('status', [1, '1'])
            ->orderBy('_id', 'desc')
            ->with(['designationRelation', 'organization'])
            ->get();

        $formatted = [];
        foreach ($records as $exp) {
            $designationTitle = $exp->designationRelation?->designation ?? 'Untitled Position';
            $orgName = $exp->organization?->organization_name ?? 'Organization';
            $desigVal = (string) ($exp->designationRelation?->_id ?? $exp->designation ?? '');
            $orgVal = (string) ($exp->organization?->_id ?? $exp->organization_id ?? '');

            $words = preg_split('/[\s,\-\(\)]+/', $orgName, -1, PREG_SPLIT_NO_EMPTY);
            $acronym = '';
            foreach ($words as $w) {
                if (ctype_alnum($w[0]) && ! in_array(strtolower($w), ['of', 'and', 'the', 'for', 'in', 'at'], true)) {
                    $acronym .= strtoupper($w[0]);
                }
            }
            if (strlen($acronym) < 2) {
                $acronym = strtoupper(substr($orgName, 0, 3));
            }

            $formatted[] = [
                'id' => (string) $exp->_id,
                'designation' => $designationTitle,
                'designation_id' => $desigVal,
                'organization' => $orgName,
                'organization_id' => $orgVal,
                'start_date' => (string) ($exp->join_date ?? ''),
                'end_date' => (string) ($exp->end_date ?? ''),
                'is_current' => (int) ($exp->current_organization ?? 0) === 1,
                'description' => (string) ($exp->description ?? ''),
                'status' => (string) ($exp->status ?? '1'),
                'logo_title' => strtoupper($orgName),
                'logo_sub' => substr($acronym, 0, 4),
            ];
        }

        return $formatted;
    }

    /**
     * Save experience record from user profile view.
     */
    public function saveExperience(array $payload): void
    {
        if (! $this->isOwnProfile || ! $this->user) {
            $this->dispatch('toast', message: 'Unauthorized.', type: 'danger', position: 'bottom');

            return;
        }

        $designationTitle = trim((string) ($payload['designation'] ?? ''));
        $orgName = trim((string) ($payload['organization'] ?? ''));
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $isCurrent = (bool) ($payload['is_current'] ?? false);
        $description = trim((string) ($payload['description'] ?? ''));
        $expId = ! empty($payload['id']) ? trim((string) $payload['id']) : null;

        if ($designationTitle === '' || $orgName === '' || $startDate === '') {
            $this->dispatch('toast', message: 'Please fill all required fields.', type: 'danger', position: 'bottom');

            return;
        }

        if (! $isCurrent && $endDate === '') {
            $this->dispatch('toast', message: 'End date is required for past roles.', type: 'danger', position: 'bottom');

            return;
        }

        if (mb_strlen($description) < 50 || mb_strlen($description) > 1500) {
            $this->dispatch('toast', message: 'Description must be between 50 and 1,500 characters.', type: 'danger', position: 'bottom');

            return;
        }

        $designationModel = Designation::recordUsage($designationTitle);
        $orgModel = Organization::recordOrFind($orgName);

        if ($expId) {
            $exp = Experience::findOrFail($expId);
        } else {
            $exp = new Experience();
            $exp->user_id = (string) $this->user->_id;
            $exp->status = 1;
        }

        $exp->designation = (string) $designationModel->_id;
        $exp->organization_id = (string) $orgModel->_id;
        $exp->join_date = Experience::formatMonthYear($startDate);
        $exp->end_date = $isCurrent ? null : Experience::formatMonthYear($endDate);
        $exp->current_organization = $isCurrent ? 1 : 0;
        $exp->description = $description;
        $exp->save();

        $strId = (string) $exp->_id;
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if (! $profile) {
            $profile = ReviewerProfile::create([
                'user_id' => (string) $this->user->_id,
                'unique_id' => (string) ($this->user->unique_id ?? ''),
                'status' => (string) $this->user->getStatusCode(),
                'experience' => [$strId],
            ]);
        } else {
            $currentIds = (array) ($profile->experience ?? []);
            if (! in_array($strId, $currentIds, true)) {
                ReviewerProfile::where('user_id', (string) $this->user->_id)->push('experience', $strId, true);
            }
        }

        $this->dispatch('toast', message: 'Experience saved successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }

    /**
     * Delete experience record from user profile.
     */
    public function deleteExperience(string $expId): void
    {
        if (! $this->isOwnProfile || ! $this->user) {
            $this->dispatch('toast', message: 'Unauthorized.', type: 'danger', position: 'bottom');

            return;
        }

        $exp = Experience::find($expId);
        if ($exp) {
            $exp->delete();
        }

        ReviewerProfile::where('user_id', (string) $this->user->_id)->pull('experience', $expId);

        $this->dispatch('toast', message: 'Experience deleted successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
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
                {{-- Section 1: About --}}
                <x-profile.about 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                />

                {{-- Section 2: Experience --}}
                <x-profile.experience 
                    :user="$user" 
                    :is-own-profile="$isOwnProfile" 
                    :experiences="$this->experiences"
                />

                <div id="education" class="bg-white dark:bg-[#111c26] border border-dashed border-zinc-300 dark:border-zinc-800 rounded-2xl p-6 text-center text-zinc-400 dark:text-zinc-500 scroll-mt-28">
                    <span class="text-2xl mb-1 inline-block">🎓</span>
                    <h3 class="font-bold font-space text-sm text-zinc-700 dark:text-zinc-300">Education</h3>
                    <p class="text-xs text-zinc-400 mt-1">Ready to build as Section 4</p>
                </div>

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
