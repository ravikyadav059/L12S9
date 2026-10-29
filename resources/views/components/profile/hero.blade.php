@props([
    'user' => null,
    'isOwnProfile' => true,
    'dummyData' => [],
])

@php
    $firstName = trim((string) ($user?->first_name ?? ''));
    $lastName = trim((string) ($user?->last_name ?? ''));
    $prefix = trim((string) ($user?->prefix ?? ''));

    $name = trim(($prefix ? $prefix . ' ' : '') . $firstName . ' ' . $lastName);
    if (empty($name)) {
        $name = trim((string) ($user?->name ?: ($user?->fullname ?: ($dummyData['name'] ?? ''))));
    }

    $scholarId = (string) ($user?->unique_id ?: ($user?->scholar_id ?: ($dummyData['scholar_id'] ?? '')));
    $avatar = $user?->avatar ?: ($dummyData['avatar'] ?? null);

    $reviewerProfile = $user?->reviewerProfile;
    $title = $reviewerProfile?->designation ?: ($dummyData['title'] ?? 'Director');
    $organization = $dummyData['organization'] ?? 'Janvi Wave LLP';
    $roleSpecialization = $dummyData['role'] ?? 'Research Journal Publisher';
    $location = $reviewerProfile?->formatted_location ?: ($dummyData['location'] ?? null);

    $publicProfileUrl = $reviewerProfile?->public_profile ?: null;
    $jobProfileUrl = $reviewerProfile?->employee_url ?: null;
    $githubUrl = $reviewerProfile?->github_url ?: null;
    $googleScholarUrl = $reviewerProfile?->google_scholar_url ?: null;
    $orcidUrl = $reviewerProfile?->orcid_url ?: null;
    $linkedinUrl = $reviewerProfile?->linkedin_url ?: null;
    $youtubeUrl = $reviewerProfile?->youtube_link ?: ($reviewerProfile?->youtube_url ?: null);
    $topExperience = $dummyData['top_experience'] ?? 'Janvi Wave LLP';
    $topEducation = $dummyData['top_education'] ?? 'Sequence Research & Development Private Limited';
    $completeness = $dummyData['completeness'] ?? 100;
@endphp

{{-- 
    .NewProfilehero & .NewProfilehero-inner (Design page/user_profile/css1.css lines 105 & 126):
    - Total top padding: 32px + 24px = 56px (pt-14)
    - Total bottom padding: 24px + 24px = 48px (pb-12)
    - Max-width: 1220px
    - Gap: 20px (gap-5)
--}}
<section class="bg-brand text-white pt-14 pb-12 px-4 relative overflow-hidden">
    {{-- .NewProfilehero-inner: max-width 1220px, margin 0 auto --}}
    <div class="max-w-[1220px] mx-auto">
        
        {{-- 
            Single direct flex container matching .NewProfilehero-inner:
            - display: flex; gap: 20px; align-items: flex-start;
        --}}
        <div class="flex flex-col lg:flex-row items-center lg:items-start gap-5 text-center lg:text-left">

            {{-- 1. .NewProfileavatar: 120px circle, 4px white border, shadow 0 8px 24px rgba(0,0,0,0.15), shrink-0 --}}
            <div class="shrink-0">
                <div class="w-[100px] h-[100px] sm:w-[120px] sm:h-[120px] rounded-full border-4 border-white shadow-[0_8px_24px_rgba(0,0,0,0.15)] overflow-hidden bg-white flex items-center justify-center">
                    @php
                        $avatarUrl = $avatar ?: 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'User') . '&background=198bea&color=fff&size=200';
                    @endphp
                    <img 
                        src="{{ $avatarUrl }}" 
                        alt="{{ $name }}" 
                        class="w-full h-full object-cover"
                        onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($name ?: 'User') }}&background=198bea&color=fff&size=200';"
                    />
                </div>
            </div>

            {{-- 2. .hero-info: flex 1, min-w-0, full width filling space up to actions --}}
            <div class="flex-1 min-w-0 w-full">
                
                {{-- .hero-name: display flex, gap 12px, margin-bottom 6px --}}
                <div class="flex items-center justify-center lg:justify-start gap-3 flex-wrap mb-1.5">
                    <h1 class="text-[28px] sm:text-[36px] font-extrabold text-white tracking-[-1px] leading-tight drop-shadow-[0_2px_8px_rgba(0,0,0,0.15)] m-0">
                        {{ $name }}
                    </h1>
                    <img 
                        src="{{ asset('images/verified-tick.png') }}" 
                        alt="Verified Scholar" 
                        title="Verified Scholar"
                        class="size-6 shrink-0 inline-block drop-shadow-xs"
                        onerror="this.style.display='none'"
                    />
                </div>

                {{-- .hero-meta: margin-bottom 18px, gap 10px --}}
                <div class="space-y-1 mb-[18px] text-white/95">
                    {{-- Title and organization --}}
                    <div class="text-[15px] font-medium leading-relaxed">
                        {{ $title }} at {{ $organization }}
                    </div>

                    {{-- Role and location --}}
                    <div class="text-[14px] text-white/90 leading-relaxed flex items-center justify-center lg:justify-start gap-2.5 flex-wrap">
                        @if(!empty($roleSpecialization))
                            <span class="inline-flex items-center gap-1">
                                <span class="text-base leading-none">📚</span>
                                <span>{{ $roleSpecialization }}</span>
                            </span>
                        @endif
                        @if(!empty($roleSpecialization) && !empty($location))
                            <span class="text-white/60 font-light">|</span>
                        @endif
                        @if(!empty($location))
                            <span>{{ $location }}</span>
                        @endif
                    </div>
                </div>

                {{-- .hero-actions: display flex, gap 8px, margin-bottom 16px --}}
                <div class="flex items-center justify-center lg:justify-start gap-2 flex-wrap mb-4">
                    {{-- Public Profile --}}
                    @if($publicProfileUrl)
                        <a 
                            href="{{ $publicProfileUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <svg class="size-4 shrink-0 text-zinc-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                            </svg>
                            <span>Public Profile</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    {{-- Job Profile --}}
                    @if($jobProfileUrl)
                        <a 
                            href="{{ $jobProfileUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <svg class="size-4 shrink-0 text-zinc-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
                            <span>Job Profile</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    {{-- GitHub --}}
                    @if($githubUrl)
                        <a 
                            href="{{ $githubUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <span class="text-sm leading-none">🔗</span>
                            <span>GitHub</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    {{-- Google Scholar --}}
                    @if($googleScholarUrl)
                        <a 
                            href="{{ $googleScholarUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <span class="text-sm leading-none">🎓</span>
                            <span>Google Scholar</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    {{-- ORCID --}}
                    @if($orcidUrl)
                        <a 
                            href="{{ $orcidUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <span class="text-sm leading-none">🔬</span>
                            <span>ORCID</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    {{-- LinkedIn --}}
                    @if($linkedinUrl)
                        <a 
                            href="{{ $linkedinUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <span class="text-sm leading-none">💼</span>
                            <span>LinkedIn</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    {{-- YouTube --}}
                    @if($youtubeUrl)
                        <a 
                            href="{{ $youtubeUrl }}" 
                            target="_blank" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:border-brand hover:text-brand hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 no-underline"
                        >
                            <span class="text-sm leading-none">📺</span>
                            <span>YouTube</span>
                            <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif
                </div>

                {{-- .profile-completeness-wrapper: padding 10px, border-radius 16px --}}
                <div class="text-left">
                    <div class="bg-white text-[#111827] p-2.5 sm:px-3.5 sm:py-2.5 rounded-2xl border-2 border-[#e5e7eb] shadow-[0_1px_3px_rgba(0,0,0,0.1)] hover:border-brand hover:shadow-[0_4px_12px_rgba(0,0,0,0.08)] transition-all duration-300">
                        {{-- .profile-completeness-info: gap 12px, margin-bottom 12px --}}
                        <div class="flex items-center justify-between gap-3 text-xs mb-2.5">
                            <span class="text-[12px] sm:text-[14px] font-medium text-[#6b7280] leading-snug">
                                🎉 <strong class="text-[#111827] font-bold">Profile Complete!</strong> You're verified and all set!
                            </span>
                            <span class="text-[14px] font-bold text-[#10b981] whitespace-nowrap min-w-[45px] text-right">
                                {{ $completeness }}%
                            </span>
                        </div>
                        {{-- .profile-completeness-bar-container --}}
                        <div class="relative w-full pt-1">
                            <div class="relative w-full bg-[#f3f4f6] h-3.5 rounded-full overflow-hidden flex items-center shadow-[inset_0_2px_4px_rgba(0,0,0,0.06)]">
                                <div 
                                    class="h-full rounded-full bg-gradient-to-r from-[#10b981] to-[#059669] shadow-[0_2px_8px_rgba(16,185,129,0.4)] transition-all duration-700 flex items-center justify-end pr-2" 
                                    style="width: {{ $completeness }}%;"
                                >
                                    <span class="text-[11px] font-bold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.2)] tracking-wide">Complete! 🎉</span>
                                </div>
                            </div>
                            {{-- 70% Threshold Marker Line & Pill --}}
                            <div class="absolute left-[70%] top-1 w-0.5 h-3.5 bg-gray-300 pointer-events-none z-10 -translate-x-1/2"></div>
                            <div class="absolute left-[70%] -top-3.5 -translate-x-1/2 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 bg-white border border-[#e5e7eb] rounded shadow-2xs pointer-events-none leading-none">
                                70%
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- 3. .NewProfilehero-actions: shrink-0, max-width 245px, items-start left-aligned --}}
            <div class="shrink-0 w-full lg:w-[245px] max-w-[245px] flex flex-col items-center lg:items-start gap-1 order-3 lg:order-none text-center lg:text-left pt-1">
                {{-- Scholar9 ID --}}
                <div class="text-[15px] font-medium text-white tracking-[-0.3px] whitespace-nowrap">
                    Scholar9 ID <span class="text-white/70 font-normal">|</span> <span class="font-bold">{{ $scholarId }}</span>
                </div>

                {{-- Primary Experience --}}
                @if($topExperience)
                    <a href="#experience" class="text-[14px] font-medium text-white/95 hover:underline truncate max-w-[245px] tracking-[-0.3px] block">
                        {{ $topExperience }}
                    </a>
                @endif

                {{-- Primary Education (wraps cleanly to 2 lines per original design) --}}
                @if($topEducation)
                    <a href="#education" class="text-[14px] font-medium text-white/95 hover:underline leading-snug max-w-[245px] tracking-[-0.3px] block">
                        {{ $topEducation }}
                    </a>
                @endif

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2 pt-2.5">
                    {{-- Share Profile --}}
                    <button 
                        type="button" 
                        x-data 
                        @click="navigator.clipboard.writeText(window.location.href); alert('Profile link copied to clipboard!');" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:bg-[#f9fafb] hover:border-brand hover:text-brand hover:-translate-y-px hover:shadow-[0_2px_6px_rgba(0,0,0,0.1)] transition-all duration-200 active:scale-95 cursor-pointer whitespace-nowrap"
                    >
                        <svg class="size-3.5 shrink-0 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                        <span>Share Profile</span>
                    </button>

                    {{-- Edit Profile (Self-view only) --}}
                    @if($isOwnProfile)
                        <button 
                            type="button" 
                            x-data
                            @click="$dispatch('open-edit-profile-modal')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white text-[#111827] text-[13px] font-semibold border border-[#e5e7eb] shadow-2xs hover:bg-[#f9fafb] hover:border-brand hover:text-brand hover:-translate-y-px hover:shadow-[0_2px_6px_rgba(0,0,0,0.1)] transition-all duration-200 active:scale-95 cursor-pointer whitespace-nowrap"
                        >
                            <svg class="size-3.5 shrink-0 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            <span>Edit Profile</span>
                        </button>
                    @endif
                </div>
            </div>

        </div>

    </div>
</section>
