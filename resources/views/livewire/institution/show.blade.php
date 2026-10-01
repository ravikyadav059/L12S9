<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\Publication;
use App\Models\ReviewerProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public int $publicationsLimit = 10;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function loadMorePublications(): void
    {
        $this->publicationsLimit += 10;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['overview', 'scholars', 'alumni', 'publications'], true) ? $tab : 'overview';
    }

    public function with(): array
    {
        $organization = Organization::query()
            ->where('slug', $this->slug)
            ->first();

        if (! $organization) {
            $organization = Organization::query()
                ->where('_id', $this->slug)
                ->firstOrFail();
        }

        $orgId = (string) $organization->_id;

        // Country name lookup
        $countryName = '';
        $rawCountry = $organization->country ?? '';
        if (! empty($rawCountry)) {
            if (is_numeric($rawCountry)) {
                $countryDoc = Cache::remember('country_item_raw_'.$rawCountry, 86400, function () use ($rawCountry) {
                    return Country::raw(fn ($col) => $col->findOne(['id' => ['$in' => [(int) $rawCountry, (string) $rawCountry]]], ['projection' => ['name' => 1]]));
                });
                $countryName = $countryDoc ? (string) ($countryDoc['name'] ?? $rawCountry) : (string) $rawCountry;
            } else {
                $countryName = (string) $rawCountry;
            }
        }

        // State name lookup
        $stateName = '';
        $rawState = $organization->state ?? '';
        if (! empty($rawState)) {
            if (is_numeric($rawState)) {
                $stateDoc = Cache::remember('state_item_raw_'.$rawState, 86400, function () use ($rawState) {
                    return State::raw(fn ($col) => $col->findOne(['id' => ['$in' => [(int) $rawState, (string) $rawState]]], ['projection' => ['name' => 1]]));
                });
                $stateName = $stateDoc ? (string) ($stateDoc['name'] ?? $rawState) : (string) $rawState;
            } else {
                $stateName = (string) $rawState;
            }
        }

        // City name lookup
        $cityName = '';
        $rawCity = $organization->city ?? '';
        if (! empty($rawCity)) {
            if (is_numeric($rawCity)) {
                $cityDoc = Cache::remember('city_item_raw_'.$rawCity, 86400, function () use ($rawCity) {
                    return City::raw(fn ($col) => $col->findOne(['id' => ['$in' => [(int) $rawCity, (string) $rawCity]]], ['projection' => ['name' => 1]]));
                });
                $cityName = $cityDoc ? (string) ($cityDoc['name'] ?? $rawCity) : (string) $rawCity;
            } else {
                $cityName = (string) $rawCity;
            }
        }

        // Metrics calculation (Scholars, Alumnus, Publications, Citations, Seminars)
        $metrics = Cache::remember('org_metrics_v6_'.$orgId, 1800, function () use ($orgId) {
            $experiences = Experience::raw(fn ($col) => $col->find(
                [
                    'organization_id' => $orgId,
                    'user_id' => ['$exists' => true, '$ne' => '', '$nin' => [null, '']],
                    'status' => ['$in' => [1, '1']],
                ],
                [
                    'projection' => [
                        'user_id' => 1,
                        'current_organization' => 1,
                    ],
                ]
            ));

            $currentUsers = [];
            $alumniUsers = [];
            $allCandidateUserIds = [];

            foreach ($experiences as $exp) {
                $uid = (string) ($exp['user_id'] ?? '');
                $curr = $exp['current_organization'] ?? null;

                if ($uid === '') {
                    continue;
                }

                $allCandidateUserIds[$uid] = true;
                if (in_array($curr, [1, '1', true], true)) {
                    $currentUsers[$uid] = true;
                } else {
                    $alumniUsers[$uid] = true;
                }
            }

            // Verify active user status (status = 1 or "1")
            $activeUserMap = [];
            $candidateUids = array_keys($allCandidateUserIds);
            if (! empty($candidateUids)) {
                $cObjIds = [];
                $cStrIds = [];
                foreach ($candidateUids as $cuid) {
                    $cuidStr = (string) $cuid;
                    $cStrIds[] = $cuidStr;
                    if (strlen($cuidStr) === 24 && ctype_xdigit($cuidStr)) {
                        $cObjIds[] = new \MongoDB\BSON\ObjectId($cuidStr);
                    }
                }

                $activeUserDocs = User::raw(fn ($col) => $col->find(
                    [
                        '_id' => ['$in' => array_merge($cObjIds, $cStrIds)],
                        'status' => ['$in' => [1, '1']],
                    ],
                    [
                        'projection' => ['_id' => 1],
                    ]
                ));

                foreach ($activeUserDocs as $au) {
                    $activeUserMap[(string) $au['_id']] = true;
                }
            }

            $currentUids = [];
            foreach (array_keys($currentUsers) as $uId) {
                if (isset($activeUserMap[$uId])) {
                    $currentUids[] = $uId;
                }
            }

            $alumniUids = [];
            foreach (array_keys($alumniUsers) as $uId) {
                if (isset($activeUserMap[$uId]) && ! in_array($uId, $currentUids, true)) {
                    $alumniUids[] = $uId;
                }
            }

            $orgPubIds = [];
            $orgSeminarIds = [];

            if (! empty($currentUids)) {
                $profiles = ReviewerProfile::raw(fn ($col) => $col->find(
                    [
                        'user_id' => ['$in' => $currentUids],
                        'status' => ['$in' => [1, '1']],
                    ],
                    ['projection' => ['publication' => 1, 'seminar' => 1]]
                ));

                foreach ($profiles as $prof) {
                    $pubs = is_array($prof['publication'] ?? null) ? $prof['publication'] : [];
                    $sems = is_array($prof['seminar'] ?? null) ? $prof['seminar'] : [];

                    foreach ($pubs as $p) {
                        if (! empty($p)) {
                            $orgPubIds[(string) $p] = true;
                        }
                    }

                    foreach ($sems as $s) {
                        if (! empty($s)) {
                            $orgSeminarIds[(string) $s] = true;
                        }
                    }
                }
            }

            $totalCitations = 0;
            $distinctPubIds = array_keys($orgPubIds);
            $validActivePubIds = [];

            if (! empty($distinctPubIds)) {
                $validObjectIds = [];
                $validStrIds = [];
                foreach ($distinctPubIds as $pid) {
                    $pidStr = (string) $pid;
                    if ($pidStr !== '') {
                        $validStrIds[] = $pidStr;
                        if (strlen($pidStr) === 24 && ctype_xdigit($pidStr)) {
                            $validObjectIds[] = new \MongoDB\BSON\ObjectId($pidStr);
                        }
                    }
                }

                if (! empty($validObjectIds) || ! empty($validStrIds)) {
                    $pubDocs = Publication::raw(fn ($col) => $col->find(
                        [
                            '_id' => ['$in' => array_merge($validObjectIds, $validStrIds)],
                            'status' => ['$in' => [1, '1']],
                        ],
                        ['projection' => ['_id' => 1, 'citations' => 1]]
                    ));

                    foreach ($pubDocs as $pd) {
                        $validActivePubIds[] = (string) $pd['_id'];
                        $totalCitations += (int) ($pd['citations'] ?? 0);
                    }
                }
            }

            return [
                'scholars' => count($currentUids),
                'alumni' => count($alumniUids),
                'publications' => count($validActivePubIds),
                'citations' => $totalCitations,
                'seminars' => count($orgSeminarIds),
                'scholar_uids' => $currentUids,
                'alumni_uids' => $alumniUids,
                'pub_ids' => $validActivePubIds,
            ];
        });

        // 1. Batch load Scholar Users
        $scholarUids = $metrics['scholar_uids'] ?? [];
        $scholarUserDocs = collect();
        if (! empty($scholarUids)) {
            $sObjIds = [];
            $sStrIds = [];
            foreach ($scholarUids as $sid) {
                $sStrIds[] = (string) $sid;
                if (strlen($sid) === 24 && ctype_xdigit($sid)) {
                    $sObjIds[] = new \MongoDB\BSON\ObjectId($sid);
                }
            }
            $scholarUserDocs = User::whereIn('_id', array_merge($sObjIds, $sStrIds))
                ->whereIn('status', [1, '1'])
                ->select(['_id', 'first_name', 'last_name', 'fullname', 'slug', 'photo', 'affiliation', 'email'])
                ->get();
        }

        // 2. Batch load Alumnus Users
        $alumniUids = $metrics['alumni_uids'] ?? [];
        $alumniUserDocs = collect();
        if (! empty($alumniUids)) {
            $aObjIds = [];
            $aStrIds = [];
            foreach ($alumniUids as $aid) {
                $aStrIds[] = (string) $aid;
                if (strlen($aid) === 24 && ctype_xdigit($aid)) {
                    $aObjIds[] = new \MongoDB\BSON\ObjectId($aid);
                }
            }
            $alumniUserDocs = User::whereIn('_id', array_merge($aObjIds, $aStrIds))
                ->whereIn('status', [1, '1'])
                ->select(['_id', 'first_name', 'last_name', 'fullname', 'slug', 'photo', 'affiliation', 'email'])
                ->get();
        }

        // 3. Batch load Publications for this institution with pagination
        $pubIds = $metrics['pub_ids'] ?? [];
        $publications = collect();
        $hasMorePublications = false;

        if (! empty($pubIds)) {
            $pObjIds = [];
            $pStrIds = [];
            foreach ($pubIds as $pid) {
                $pidStr = (string) $pid;
                if ($pidStr !== '') {
                    $pStrIds[] = $pidStr;
                    if (strlen($pidStr) === 24 && ctype_xdigit($pidStr)) {
                        $pObjIds[] = new \MongoDB\BSON\ObjectId($pidStr);
                    }
                }
            }

            $pubQuery = Publication::whereIn('_id', array_merge($pObjIds, $pStrIds))
                ->whereIn('status', [1, '1'])
                ->with(['reviewerJournal' => fn ($q) => $q->select(['_id', 'journal_title', 'slug', 'journal_photo', 'status'])])
                ->select([
                    '_id',
                    'title',
                    'slug',
                    'journal_title',
                    'journal_name',
                    'authors',
                    'registered_co_author',
                    'unregistered_co_author',
                    'published_date',
                    'publication_month_year',
                    'created_at',
                    'user_id',
                    'status',
                ])
                ->latest();

            $pubResults = $pubQuery->limit($this->publicationsLimit + 1)->get();
            $hasMorePublications = $pubResults->count() > $this->publicationsLimit;
            $publications = $hasMorePublications ? $pubResults->slice(0, $this->publicationsLimit) : $pubResults;
        }

        // Preload active registered authors for these publications (only status = 1 or "1")
        $activeAuthorsMap = [];
        if ($publications->isNotEmpty()) {
            $authorUserIds = [];
            foreach ($publications as $pub) {
                if (! empty($pub->registered_co_author) && is_array($pub->registered_co_author)) {
                    foreach ($pub->registered_co_author as $uid) {
                        if (! empty($uid)) {
                            $authorUserIds[] = (string) $uid;
                        }
                    }
                }
                if (! empty($pub->user_id)) {
                    $authorUserIds[] = (string) $pub->user_id;
                }
            }
            $authorUserIds = array_values(array_unique($authorUserIds));

            if (! empty($authorUserIds)) {
                $aObjIds = [];
                $aStrIds = [];
                foreach ($authorUserIds as $auid) {
                    $aStrIds[] = $auid;
                    if (strlen($auid) === 24 && ctype_xdigit($auid)) {
                        try {
                            $aObjIds[] = new \MongoDB\BSON\ObjectId($auid);
                        } catch (\Exception $e) {
                        }
                    }
                }

                $activeUsers = User::whereIn('_id', array_merge($aObjIds, $aStrIds))
                    ->whereIn('status', [1, '1'])
                    ->get(['_id', 'name', 'first_name', 'last_name', 'email', 'slug', 'avatar', 'status']);

                foreach ($activeUsers as $u) {
                    $activeAuthorsMap[(string) $u->_id] = $u;
                }
            }
        }

        return [
            'organization' => $organization,
            'countryName' => $countryName,
            'stateName' => $stateName,
            'cityName' => $cityName,
            'metrics' => $metrics,
            'scholarUserDocs' => $scholarUserDocs,
            'alumniUserDocs' => $alumniUserDocs,
            'publications' => $publications,
            'activeAuthorsMap' => $activeAuthorsMap,
            'hasMorePublications' => $hasMorePublications,
        ];
    }
}; ?>

<div class="min-h-screen bg-zinc-50/70 dark:bg-zinc-950 font-sans pb-8 sm:pb-12">
    @php
        $orgName = $organization->organization_name ?? 'Academic Institution';
        $orgType = strtolower((string)($organization->organization_type ?? 'education'));
        
        $locationParts = array_filter([$cityName, $stateName, $countryName], fn($p) => !empty($p));
        $locationText = implode(', ', $locationParts);

        $logoSrc = null;
        if (!empty($organization->logo)) {
            $logoSrc = str_starts_with($organization->logo, 'http') ? $organization->logo : asset($organization->logo);
        }

        $shareUrl = url('institution/' . ($organization->slug ?: $organization->_id));
    @endphp

    <!-- ================= TOP STICKY BAR: BACK BUTTON, TITLE ON SCROLL & SHARE BUTTON ================= -->
    <div 
        x-data="{ 
            showStickyTitle: false,
            checkScroll() {
                const titleEl = document.getElementById('hero-institution-title');
                if (titleEl) {
                    const rect = titleEl.getBoundingClientRect();
                    this.showStickyTitle = rect.bottom <= 70;
                } else {
                    this.showStickyTitle = window.scrollY > 150;
                }
            }
        }" 
        @scroll.window="checkScroll()"
        x-init="checkScroll()"
        class="sticky top-14 sm:top-16 z-40 w-full bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-b border-zinc-200/80 dark:border-zinc-800 shadow-xs transition-all"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 sm:py-3 flex items-center justify-between gap-3 text-xs sm:text-sm">
            <!-- Left: Back Button -->
            <a 
                href="{{ route('institutions.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white rounded-lg transition-all shadow-2xs shrink-0 cursor-pointer group"
            >
                <flux:icon name="arrow-left" class="size-3.5 transition-transform group-hover:-translate-x-0.5" />
                <span class="hidden sm:inline">Back to Institutions</span>
                <span class="sm:hidden">Back</span>
            </a>

            <!-- Center: Sticky Institution Title (Revealed when hero title scrolls out of view) -->
            <div 
                x-show="showStickyTitle"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-2"
                class="flex items-center gap-2 min-w-0 flex-1 border-l border-zinc-200 dark:border-zinc-800 pl-2.5 sm:pl-4"
                style="display: none;"
            >
                <div class="w-5 h-5 rounded-md text-white flex items-center justify-center text-[9px] font-bold shrink-0 shadow-2xs bg-[#198BEA]">
                    {{ mb_substr($orgName, 0, 2) }}
                </div>
                <span class="text-zinc-900 dark:text-zinc-100 font-semibold text-xs sm:text-sm truncate">
                    {{ $orgName }}
                </span>
            </div>

            <!-- Right: Share Action Button (Always Accessible) -->
            <button 
                type="button" 
                @click="$dispatch('open-share-modal', { url: @js($shareUrl), title: @js($orgName), type: 'institution', header: 'Share Institution', subtitle: 'Share this institution across networks' })"
                class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white text-zinc-700 dark:text-zinc-300 text-xs font-semibold transition shadow-2xs shrink-0 cursor-pointer ml-auto"
                title="Share this Institution"
            >
                <flux:icon name="share" class="size-3.5" />
                <span class="hidden sm:inline">Share</span>
            </button>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            <!-- Left Main Column -->
            <div class="flex-1 min-w-0 w-full space-y-6">
                
                <!-- Institution Profile Header Card -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
                    <div class="flex flex-col sm:flex-row items-start gap-6">
                        <!-- Logo -->
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                            @if ($logoSrc)
                                <img 
                                    src="{{ $logoSrc }}" 
                                    alt="{{ $orgName }}" 
                                    class="w-full h-full object-contain p-3"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                />
                                <div class="w-full h-full hidden items-center justify-center bg-[#198BEA] text-white font-bold text-3xl">
                                    {{ mb_substr($orgName, 0, 2) }}
                                </div>
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-[#eaf5ff] dark:bg-sky-950/40 text-[#198BEA] dark:text-sky-400">
                                    <svg class="w-12 h-12 opacity-85" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2L2 7v2h20V7L12 2zM4 11v8h3v-8H4zm6 0v8h4v-8h-4zm7 0v8h3v-8h-3zM2 21v2h20v-2H2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>

                        <!-- Info Column -->
                        <div class="flex-1 min-w-0 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if(!empty($countryName))
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                        <svg class="w-3.5 h-3.5 text-[#198BEA] dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>{{ $countryName }}</span>
                                    </span>
                                @endif

                                @if($orgType === 'education' || $orgType === 'university' || $orgType === 'college')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                        Education
                                    </span>
                                @elseif($orgType === 'corporate' || $orgType === 'company')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                        Corporate
                                    </span>
                                @elseif($orgType === 'research')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Research
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                        {{ ucfirst($orgType) }}
                                    </span>
                                @endif
                            </div>

                            <h1 id="hero-institution-title" class="text-lg sm:text-xl lg:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight leading-snug">
                                {{ $orgName }}
                            </h1>

                            @if(!empty($organization->affiliated_university))
                                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    <svg class="w-4 h-4 text-[#198BEA] shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2 1 7l11 5 9-4.09V15h2V7L12 2z"/>
                                        <circle cx="12" cy="11.5" r="3"/>
                                        <path d="M6 19.5c0-2 2.69-3.5 6-3.5s6 1.5 6 3.5V21H6v-1.5z"/>
                                    </svg>
                                    <span>Affiliated University: <span class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $organization->affiliated_university }}</span></span>
                                </div>
                            @endif

                            @if(!empty($organization->address) || !empty($locationText))
                                <div class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <svg class="w-4 h-4 text-zinc-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>
                                        @if(!empty($organization->address))
                                            {{ $organization->address }}
                                            @if(!empty($locationText) && !str_contains($organization->address, $locationText))
                                                • {{ $locationText }}
                                            @endif
                                        @else
                                            {{ $locationText }}
                                        @endif
                                        @if(!empty($organization->zipcode) && !str_contains((string)$organization->address, (string)$organization->zipcode))
                                            ({{ $organization->zipcode }})
                                        @endif
                                    </span>
                                </div>
                            @endif

                            <!-- Key Academic & Research Metrics Inline Row (Matching Image 1) -->
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs sm:text-[13px] font-bold tracking-wide text-[#3B7CA7] dark:text-sky-400 uppercase pt-2">
                                <div>
                                    PUBLICATION -{{ $metrics['publications'] == 0 ? '00' : $metrics['publications'] }}
                                </div>
                                <div>
                                    CITATIONS -{{ $metrics['citations'] == 0 ? '00' : $metrics['citations'] }}
                                </div>
                                <div>
                                    CONFERENCES/SEMINAR -{{ $metrics['seminars'] == 0 ? '00' : $metrics['seminars'] }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Row -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-2.5">
                            @if(!empty($organization->website_url))
                                <a 
                                    href="{{ $organization->website_url }}" 
                                    target="_blank" 
                                    rel="noopener noreferrer" 
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs font-semibold rounded-lg shadow-xs hover:shadow transition cursor-pointer"
                                >
                                    <span>Visit Official Website</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @endif
                        </div>

                        <!-- Share Button -->
                        <button 
                            type="button" 
                            @click="$dispatch('open-share-modal', { url: @js($shareUrl), title: @js($orgName), type: 'institution', header: 'Share Institution', subtitle: 'Share this institution across networks' })"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 text-xs font-semibold rounded-lg transition shadow-2xs cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                            </svg>
                            <span>Share</span>
                        </button>
                    </div>
                </div>

                <!-- ================= INTERACTIVE TABS NAVIGATION ================= -->
                <div class="flex items-center gap-1.5 p-1.5 bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl shadow-xs overflow-x-auto">
                    <!-- 1. Overview Tab -->
                    <button 
                        type="button" 
                        wire:click="setTab('overview')" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap {{ $tab === 'overview' ? 'bg-[#198BEA] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon name="information-circle" class="size-4" />
                        <span>Overview & About</span>
                    </button>

                    <!-- 2. Scholars Tab -->
                    <button 
                        type="button" 
                        wire:click="setTab('scholars')" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap {{ $tab === 'scholars' ? 'bg-[#198BEA] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon name="academic-cap" class="size-4" />
                        <span>Scholars</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $tab === 'scholars' ? 'bg-white/25 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}">
                            {{ $scholarUserDocs->count() }}
                        </span>
                    </button>

                    <!-- 3. Alumnus Tab -->
                    <button 
                        type="button" 
                        wire:click="setTab('alumni')" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap {{ $tab === 'alumni' ? 'bg-[#198BEA] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon name="user-group" class="size-4" />
                        <span>Alumnus</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $tab === 'alumni' ? 'bg-white/25 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}">
                            {{ $alumniUserDocs->count() }}
                        </span>
                    </button>

                    <!-- 4. Publications Tab -->
                    <button 
                        type="button" 
                        wire:click="setTab('publications')" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap {{ $tab === 'publications' ? 'bg-[#198BEA] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                    >
                        <flux:icon name="document-text" class="size-4" />
                        <span>Publications</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $tab === 'publications' ? 'bg-white/25 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}">
                            {{ $metrics['publications'] }}
                        </span>
                    </button>
                </div>

                <!-- ================= TAB CONTENT PANELS ================= -->

                <!-- TAB 1: OVERVIEW & ABOUT -->
                @if($tab === 'overview')
                    <div class="space-y-6">
                        <!-- About Institution Card -->
                        @if(!empty($organization->description))
                            <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-4">
                                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">
                                    About {{ $orgName }}
                                </h2>
                                <div class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed space-y-3 whitespace-pre-line">
                                    {{ $organization->description }}
                                </div>
                            </div>
                        @endif

                        <!-- Institution Details Overview Card (3-column layout) -->
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-4">
                            <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">
                                Institution Information
                            </h2>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs sm:text-sm">
                                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3.5 sm:p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                    <dt class="text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">Country</dt>
                                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $countryName ?: 'N/A' }}</dd>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3.5 sm:p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                    <dt class="text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">State / Province</dt>
                                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $stateName ?: 'N/A' }}</dd>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3.5 sm:p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                    <dt class="text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">City</dt>
                                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $cityName ?: 'N/A' }}</dd>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3.5 sm:p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                    <dt class="text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">Postal / Zip Code</dt>
                                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $organization->zipcode ?: 'N/A' }}</dd>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3.5 sm:p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                    <dt class="text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">Affiliated University</dt>
                                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $organization->affiliated_university ?: 'N/A' }}</dd>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-3.5 sm:p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
                                    <dt class="text-[11px] font-semibold uppercase text-zinc-400 tracking-wider">Organization Type</dt>
                                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ ucfirst($orgType) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif

                <!-- TAB 2: SCHOLARS -->
                @if($tab === 'scholars')
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">
                                    Active Scholars & Faculty
                                </h2>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Researchers and faculty members currently affiliated with {{ $orgName }}
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                {{ $scholarUserDocs->count() }} Scholars
                            </span>
                        </div>

                        @if($scholarUserDocs->isNotEmpty())
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($scholarUserDocs as $scholar)
                                    @php
                                        $scholarSlug = $scholar->slug ?: (string)$scholar->_id;
                                        $profileUrl = url('profile/' . $scholarSlug);
                                    @endphp
                                    <div class="flex items-center justify-between gap-3.5 p-4 rounded-2xl bg-zinc-50/70 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/70 hover:border-[#198BEA] dark:hover:border-[#198BEA] transition group">
                                        <div class="flex items-center gap-3.5 min-w-0">
                                            <img 
                                                src="{{ $scholar->avatar }}" 
                                                alt="{{ $scholar->name }}" 
                                                class="w-11 h-11 rounded-full object-cover shrink-0 border border-zinc-200 dark:border-zinc-700 shadow-2xs" 
                                                loading="lazy"
                                            />
                                            <div class="min-w-0">
                                                <a href="{{ $profileUrl }}" wire:navigate class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors truncate block">
                                                    {{ $scholar->name }}
                                                </a>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                                    {{ $scholar->affiliation ?: 'Scholar' }}
                                                </p>
                                            </div>
                                        </div>
                                        <a 
                                            href="{{ $profileUrl }}" 
                                            wire:navigate 
                                            class="shrink-0 p-2 rounded-xl bg-white dark:bg-zinc-700 text-zinc-400 hover:text-[#198BEA] dark:hover:text-sky-400 border border-zinc-200 dark:border-zinc-600 transition shadow-2xs"
                                            title="View Profile"
                                        >
                                            <flux:icon name="arrow-right" class="size-4" />
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-10 space-y-2">
                                <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                    <flux:icon name="academic-cap" class="size-6" />
                                </div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">No Active Scholars Found</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">
                                    There are currently no active scholars registered for this institution.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- TAB 3: ALUMNUS -->
                @if($tab === 'alumni')
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">
                                    Institution Alumnus
                                </h2>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Researchers and scholars who previously worked or studied at {{ $orgName }}
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                {{ $alumniUserDocs->count() }} Alumnus
                            </span>
                        </div>

                        @if($alumniUserDocs->isNotEmpty())
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($alumniUserDocs as $alumnus)
                                    @php
                                        $alumnusSlug = $alumnus->slug ?: (string)$alumnus->_id;
                                        $profileUrl = url('profile/' . $alumnusSlug);
                                    @endphp
                                    <div class="flex items-center justify-between gap-3.5 p-4 rounded-2xl bg-zinc-50/70 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/70 hover:border-[#198BEA] dark:hover:border-[#198BEA] transition group">
                                        <div class="flex items-center gap-3.5 min-w-0">
                                            <img 
                                                src="{{ $alumnus->avatar }}" 
                                                alt="{{ $alumnus->name }}" 
                                                class="w-11 h-11 rounded-full object-cover shrink-0 border border-zinc-200 dark:border-zinc-700 shadow-2xs" 
                                                loading="lazy"
                                            />
                                            <div class="min-w-0">
                                                <a href="{{ $profileUrl }}" wire:navigate class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors truncate block">
                                                    {{ $alumnus->name }}
                                                </a>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                                    {{ $alumnus->affiliation ?: 'Alumnus' }}
                                                </p>
                                            </div>
                                        </div>
                                        <a 
                                            href="{{ $profileUrl }}" 
                                            wire:navigate 
                                            class="shrink-0 p-2 rounded-xl bg-white dark:bg-zinc-700 text-zinc-400 hover:text-[#198BEA] dark:hover:text-sky-400 border border-zinc-200 dark:border-zinc-600 transition shadow-2xs"
                                            title="View Profile"
                                        >
                                            <flux:icon name="arrow-right" class="size-4" />
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-10 space-y-2">
                                <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                    <flux:icon name="user-group" class="size-6" />
                                </div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">No Alumnus Records Found</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">
                                    There are currently no alumnus records listed for this institution.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- TAB 4: PUBLICATIONS -->
                @if($tab === 'publications')
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
                        <!-- Top Header Row -->
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">
                                        Research Publications
                                    </h2>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                        {{ $metrics['publications'] }} Total Papers
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Published scientific papers and articles by scholars of {{ $orgName }}
                                </p>
                            </div>
                        </div>

                        @if($publications->isNotEmpty())
                            <!-- PUBLICATIONS CARD LIST -->
                            <div class="space-y-3.5">
                                @foreach ($publications as $index => $pub)
                                    @php
                                        $pubSlug = $pub->slug ?: (string)$pub->_id;
                                        $pubUrl  = url('publication-detail/' . $pubSlug);
                                        $pubTitle = $pub->title ?? 'Untitled Publication';
                                        
                                         // Formatted Published Date
                                        $pubDate = null;
                                        if (!empty($pub->published_date)) {
                                            $pubDate = is_string($pub->published_date) ? $pub->published_date : date('d M Y', strtotime((string)$pub->published_date));
                                        } elseif (!empty($pub->publication_month_year)) {
                                            $pubDate = $pub->publication_month_year;
                                        } elseif (!empty($pub->created_at)) {
                                            $pubDate = date('d M Y', strtotime((string)$pub->created_at));
                                        }

                                        // Resolve Journal Title & Slug via relationship
                                        $journalTitle = $pub->reviewerJournal?->journal_title;
                                        if (empty($journalTitle)) {
                                            if (!empty($pub->journal_name) && !(strlen($pub->journal_name) === 24 && ctype_xdigit($pub->journal_name))) {
                                                $journalTitle = $pub->journal_name;
                                            } elseif (!empty($pub->journal_title) && !(strlen((string)$pub->journal_title) === 24 && ctype_xdigit((string)$pub->journal_title))) {
                                                $journalTitle = (string)$pub->journal_title;
                                            }
                                        }
                                        $journalSlug = $pub->reviewerJournal?->slug ?? null;

                                        // Resolve ONLY authors with status 1 or "1"
                                        $activeAuthorList = [];
                                        
                                        // 1. Registered co-authors
                                        if (!empty($pub->registered_co_author) && is_array($pub->registered_co_author)) {
                                            foreach ($pub->registered_co_author as $uid) {
                                                $u = $activeAuthorsMap[(string)$uid] ?? null;
                                                if ($u) {
                                                    $fullName = trim($u->name ?: (($u->first_name ?? '') . ' ' . ($u->last_name ?? ''))) ?: ($u->email ?? 'Author');
                                                    $activeAuthorList[] = [
                                                        'name' => $fullName,
                                                        'slug' => $u->slug ?? null,
                                                        'is_registered' => true,
                                                    ];
                                                }
                                            }
                                        }
                                        
                                        // 2. Primary submitter user_id
                                        if (!empty($pub->user_id)) {
                                            $u = $activeAuthorsMap[(string)$pub->user_id] ?? null;
                                            if ($u) {
                                                $fullName = trim($u->name ?: (($u->first_name ?? '') . ' ' . ($u->last_name ?? ''))) ?: ($u->email ?? 'Author');
                                                $exists = false;
                                                foreach ($activeAuthorList as $existing) {
                                                    if ($existing['name'] === $fullName) {
                                                        $exists = true;
                                                        break;
                                                    }
                                                }
                                                if (!$exists) {
                                                    array_unshift($activeAuthorList, [
                                                        'name' => $fullName,
                                                        'slug' => $u->slug ?? null,
                                                        'is_registered' => true,
                                                    ]);
                                                }
                                            }
                                        }

                                        // 3. Fallback to unregistered authors if no registered active author exists
                                        if (empty($activeAuthorList)) {
                                            if (!empty($pub->unregistered_co_author) && is_array($pub->unregistered_co_author)) {
                                                foreach ($pub->unregistered_co_author as $unreg) {
                                                    $name = is_array($unreg) ? ($unreg['name'] ?? '') : (is_string($unreg) ? $unreg : '');
                                                    if (trim($name) !== '') {
                                                        $activeAuthorList[] = [
                                                            'name' => trim($name),
                                                            'slug' => null,
                                                            'is_registered' => false,
                                                        ];
                                                    }
                                                }
                                            }
                                            if (empty($activeAuthorList) && !empty($pub->authors) && is_array($pub->authors)) {
                                                foreach ($pub->authors as $auth) {
                                                    $name = is_array($auth) ? ($auth['name'] ?? '') : (is_string($auth) ? $auth : '');
                                                    if (trim($name) !== '') {
                                                        $activeAuthorList[] = [
                                                            'name' => trim($name),
                                                            'slug' => null,
                                                            'is_registered' => false,
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    @endphp

                                    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 hover:border-[#198BEA]/40 dark:hover:border-[#198BEA]/40 hover:shadow-md transition-all group">
                                        <div class="flex items-start gap-3.5">
                                            <!-- Icon Badge -->
                                            <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-[#198BEA] dark:text-sky-400 flex items-center justify-center shrink-0 mt-0.5 border border-sky-100 dark:border-sky-900/40">
                                                <flux:icon name="document-text" class="size-5" />
                                            </div>

                                            <!-- Content Area -->
                                            <div class="flex-1 min-w-0 space-y-2">
                                                <!-- Row 1: Publication Title (Clickable) -->
                                                <a 
                                                    href="{{ $pubUrl }}" 
                                                    wire:navigate 
                                                    class="text-sm sm:text-base font-bold text-zinc-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors leading-snug block"
                                                >
                                                    {{ $pubTitle }}
                                                </a>

                                                <!-- Row 2: Authors -->
                                                <div class="flex items-center gap-1.5 flex-wrap text-xs text-zinc-600 dark:text-zinc-400">
                                                    <flux:icon name="users" class="size-3.5 text-zinc-400 shrink-0" />
                                                    @if(!empty($activeAuthorList))
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            @foreach($activeAuthorList as $author)
                                                                @if(!empty($author['slug']))
                                                                    <a 
                                                                        href="{{ url('profile/' . $author['slug']) }}" 
                                                                        wire:navigate 
                                                                        class="font-medium text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] dark:hover:text-sky-400 hover:underline transition-colors"
                                                                    >
                                                                        {{ $author['name'] }}
                                                                    </a>
                                                                @else
                                                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $author['name'] }}</span>
                                                                @endif
                                                                @if(!$loop->last)
                                                                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="italic text-zinc-400 text-xs">Author information not available</span>
                                                    @endif
                                                </div>

                                                <!-- Row 3: Journal Title & Published Date (Below Authors) -->
                                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400 pt-0.5">
                                                    <!-- Journal Title -->
                                                    @if(!empty($journalTitle))
                                                        <div class="inline-flex items-center gap-1.5 min-w-0 max-w-full">
                                                            <flux:icon name="book-open" class="size-3.5 text-[#198BEA] dark:text-sky-400 shrink-0" />
                                                            @if(!empty($journalSlug))
                                                                <a 
                                                                    href="{{ url('journal/' . $journalSlug) }}" 
                                                                    wire:navigate 
                                                                    class="font-medium text-[#198BEA] dark:text-sky-400 hover:underline truncate max-w-[280px] sm:max-w-md transition-colors"
                                                                    title="{{ $journalTitle }}"
                                                                >
                                                                    {{ $journalTitle }}
                                                                </a>
                                                            @else
                                                                <span class="font-medium text-zinc-700 dark:text-zinc-300 truncate max-w-[280px] sm:max-w-md" title="{{ $journalTitle }}">
                                                                    {{ $journalTitle }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    <!-- Published Date -->
                                                    @if(!empty($pubDate))
                                                        <div class="inline-flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                                            <flux:icon name="calendar" class="size-3.5 text-zinc-400 shrink-0" />
                                                            <span>Published: <strong class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $pubDate }}</strong></span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if ($hasMorePublications)
                                <div class="pt-4 text-center">
                                    <button 
                                        type="button" 
                                        wire:click="loadMorePublications" 
                                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:border-[#198BEA] text-[#198BEA] dark:text-sky-400 text-xs font-semibold shadow-xs hover:shadow transition cursor-pointer"
                                    >
                                        <flux:icon name="arrow-path" wire:loading wire:target="loadMorePublications" class="size-3.5 animate-spin text-[#198BEA]" />
                                        <span wire:loading.remove wire:target="loadMorePublications">Load More Publications</span>
                                        <span wire:loading wire:target="loadMorePublications">Loading...</span>
                                    </button>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-10 space-y-2">
                                <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                    <flux:icon name="document-text" class="size-6" />
                                </div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">No Publications Found</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">
                                    No publications have been affiliated with this institution's scholars yet.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

            </div>

            <!-- Right Sidebar Container -->
            <x-article.sidebar />

        </div>
    </div>
</div>
