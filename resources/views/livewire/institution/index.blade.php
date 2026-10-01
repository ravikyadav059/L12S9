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
    public int $perPage = 10;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $typeFilter = 'all';

    #[Url(except: '')]
    public string $countryFilter = '';

    #[Url(except: 'name_asc')]
    public string $sortBy = 'name_asc';

    public function mount(): void
    {
        if (session()->has('institutions_per_page')) {
            $this->perPage = max(10, (int) session('institutions_per_page'));
        }
    }

    public function updatingSearch(): void
    {
        $this->perPage = 10;
        session(['institutions_per_page' => 10]);
    }

    public function updatingTypeFilter(): void
    {
        $this->perPage = 10;
        session(['institutions_per_page' => 10]);
    }

    public function updatingCountryFilter(): void
    {
        $this->perPage = 10;
        session(['institutions_per_page' => 10]);
    }

    public function updatingSortBy(): void
    {
        $this->perPage = 10;
        session(['institutions_per_page' => 10]);
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
        session(['institutions_per_page' => $this->perPage]);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = 'all';
        $this->countryFilter = '';
        $this->sortBy = 'name_asc';
        $this->perPage = 10;
        session(['institutions_per_page' => 10]);
    }

    public function with(): array
    {
        $query = Organization::query()
            ->select([
                '_id',
                'organization_name',
                'address',
                'city',
                'state',
                'country',
                'zipcode',
                'affiliated_university',
                'website_url',
                'organization_type',
                'status',
                'logo',
                'serial_number',
                'slug',
                'description',
                'created_at',
            ])
            ->whereNotNull('organization_name')
            ->where('organization_name', '!=', '')
            ->where(function ($q) {
                $q->where('status', 1)->orWhere('status', '1');
            });

        // Search Filter (name, affiliated university, address, city, state, country)
        $searchClean = trim((string) ($this->search ?? ''));
        if ($searchClean !== '' && mb_strlen($searchClean) >= 2) {
            $query->where(function ($q) use ($searchClean) {
                $regex = new \MongoDB\BSON\Regex($searchClean, 'i');
                $q->where('organization_name', 'regex', $regex)
                    ->orWhere('affiliated_university', 'regex', $regex)
                    ->orWhere('city', 'regex', $regex)
                    ->orWhere('state', 'regex', $regex)
                    ->orWhere('country', 'regex', $regex)
                    ->orWhere('address', 'regex', $regex);
            });
        }

        // Type Filter
        if ($this->typeFilter !== 'all') {
            $query->where('organization_type', $this->typeFilter);
        }

        // Country Filter
        if ($this->countryFilter !== '') {
            $query->where(function ($q) {
                $q->where('country', $this->countryFilter)
                    ->orWhere('country', (int) $this->countryFilter);
            });
        }

        // Sorting
        switch ($this->sortBy) {
            case 'name_desc':
                $query->orderBy('organization_name', 'desc');
                break;
            case 'serial_desc':
                $query->orderByDesc('serial_number');
                break;
            case 'serial_asc':
                $query->orderBy('serial_number', 'asc');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'name_asc':
            default:
                $query->orderBy('organization_name', 'asc');
                break;
        }

        // Single-pass check for pagination
        $results = $query->limit($this->perPage + 1)->get();
        $hasMore = $results->count() > $this->perPage;
        $organizations = $hasMore ? $results->slice(0, $this->perPage) : $results;

        // Base total count cached for 1 hour
        $baseTotalCount = Cache::remember('total_active_organizations_count_v4', 3600, function () {
            return Organization::whereNotNull('organization_name')
                ->where('organization_name', '!=', '')
                ->where(function ($q) {
                    $q->where('status', 1)->orWhere('status', '1');
                })
                ->count();
        });

        // Countries list for filter dropdown cached for 24 hours
        $allCountries = Cache::remember('all_countries_lookup_v3', 86400, function () {
            $docs = Country::raw(fn ($col) => $col->find([], ['projection' => ['id' => 1, 'name' => 1, 'sortname' => 1]]));
            $list = [];
            foreach ($docs as $d) {
                if (isset($d['id']) && isset($d['name'])) {
                    $list[] = [
                        'id' => (string) $d['id'],
                        'name' => (string) $d['name'],
                        'sortname' => (string) ($d['sortname'] ?? ''),
                    ];
                }
            }
            usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $list;
        });

        // Country name lookup dictionary (supports both integer and string keys)
        $countryMap = Cache::remember('country_id_to_name_map_v3', 86400, function () {
            $docs = Country::raw(fn ($col) => $col->find([], ['projection' => ['id' => 1, 'name' => 1]]));
            $map = [];
            foreach ($docs as $d) {
                if (isset($d['id']) && isset($d['name'])) {
                    $map[(string) $d['id']] = (string) $d['name'];
                    $map[(int) $d['id']] = (string) $d['name'];
                }
            }

            return $map;
        });

        // Batch resolve state names for visible organizations
        $stateIds = $organizations->pluck('state')
            ->filter(fn ($v) => ! empty($v))
            ->unique()
            ->values()
            ->all();

        $stateMap = ! empty($stateIds) ? Cache::remember('state_map_v3_'.md5(json_encode($stateIds)), 86400, function () use ($stateIds) {
            $filterIds = array_merge(array_map('strval', $stateIds), array_map('intval', $stateIds));
            $docs = State::raw(fn ($col) => $col->find(['id' => ['$in' => $filterIds]], ['projection' => ['id' => 1, 'name' => 1]]));
            $map = [];
            foreach ($docs as $s) {
                if (isset($s['id']) && isset($s['name'])) {
                    $map[(string) $s['id']] = (string) $s['name'];
                    $map[(int) $s['id']] = (string) $s['name'];
                }
            }

            return $map;
        }) : [];

        // Batch resolve city names for visible organizations
        $cityIds = $organizations->pluck('city')
            ->filter(fn ($v) => ! empty($v))
            ->unique()
            ->values()
            ->all();

        $cityMap = ! empty($cityIds) ? Cache::remember('city_map_v3_'.md5(json_encode($cityIds)), 86400, function () use ($cityIds) {
            $filterIds = array_merge(array_map('strval', $cityIds), array_map('intval', $cityIds));
            $docs = City::raw(fn ($col) => $col->find(['id' => ['$in' => $filterIds]], ['projection' => ['id' => 1, 'name' => 1]]));
            $map = [];
            foreach ($docs as $ct) {
                if (isset($ct['id']) && isset($ct['name'])) {
                    $map[(string) $ct['id']] = (string) $ct['name'];
                    $map[(int) $ct['id']] = (string) $ct['name'];
                }
            }

            return $map;
        }) : [];

        // 5. Batch calculate metrics (Scholars, Alumni, Publications, Citations, Seminars) for visible organizations
        $orgIds = $organizations->pluck('_id')->map(fn ($id) => (string) $id)->all();
        $orgMetricsMap = [];
        $allUserIds = [];

        if (! empty($orgIds)) {
            $missingOrgIds = [];
            foreach ($orgIds as $oid) {
                $cached = Cache::get('org_metrics_v6_'.$oid);
                if ($cached !== null) {
                    $orgMetricsMap[$oid] = $cached;
                    if (! empty($cached['scholar_uids'])) {
                        $allUserIds = array_merge($allUserIds, $cached['scholar_uids']);
                    }
                    if (! empty($cached['alumni_uids'])) {
                        $allUserIds = array_merge($allUserIds, $cached['alumni_uids']);
                    }
                } else {
                    $missingOrgIds[] = $oid;
                }
            }

            if (! empty($missingOrgIds)) {
                $experiences = Experience::raw(fn ($col) => $col->find(
                    [
                        'organization_id' => ['$in' => $missingOrgIds],
                        'user_id' => ['$exists' => true, '$ne' => '', '$nin' => [null, '']],
                        'status' => ['$in' => [1, '1']],
                    ],
                    [
                        'projection' => [
                            'organization_id' => 1,
                            'user_id' => 1,
                            'current_organization' => 1,
                        ],
                    ]
                ));

                $currentUsersByOrg = [];
                $alumniUsersByOrg = [];
                $allCandidateUserIds = [];

                foreach ($experiences as $exp) {
                    $oid = (string) ($exp['organization_id'] ?? '');
                    $uid = (string) ($exp['user_id'] ?? '');
                    $curr = $exp['current_organization'] ?? null;

                    if ($oid === '' || $uid === '') {
                        continue;
                    }

                    $allCandidateUserIds[$uid] = true;
                    $isCurrent = in_array($curr, [1, '1', true], true);
                    if ($isCurrent) {
                        $currentUsersByOrg[$oid][$uid] = true;
                    } else {
                        $alumniUsersByOrg[$oid][$uid] = true;
                    }
                }

                // Verify active user status (status = 1 or "1") for all candidates
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

                // Filter current and alumni lists to only active users
                $allCurrentScholarUserIds = [];
                foreach ($currentUsersByOrg as $oKey => $uList) {
                    foreach (array_keys($uList) as $uId) {
                        if (isset($activeUserMap[$uId])) {
                            $allCurrentScholarUserIds[$uId] = true;
                        } else {
                            unset($currentUsersByOrg[$oKey][$uId]);
                        }
                    }
                }

                foreach ($alumniUsersByOrg as $oKey => $uList) {
                    foreach (array_keys($uList) as $uId) {
                        if (! isset($activeUserMap[$uId])) {
                            unset($alumniUsersByOrg[$oKey][$uId]);
                        }
                    }
                }

                $distinctCurrentScholarUserIds = array_keys($allCurrentScholarUserIds);

                $profilesByUser = [];
                $allPubIds = [];

                if (! empty($distinctCurrentScholarUserIds)) {
                    $profiles = ReviewerProfile::raw(fn ($col) => $col->find(
                        [
                            'user_id' => ['$in' => $distinctCurrentScholarUserIds],
                            'status' => ['$in' => [1, '1']],
                        ],
                        [
                            'projection' => [
                                'user_id' => 1,
                                'publication' => 1,
                                'seminar' => 1,
                            ],
                        ]
                    ));

                    foreach ($profiles as $prof) {
                        $uid = (string) ($prof['user_id'] ?? '');
                        $pubs = is_array($prof['publication'] ?? null) ? $prof['publication'] : [];
                        $sems = is_array($prof['seminar'] ?? null) ? $prof['seminar'] : [];

                        $cleanPubs = [];
                        foreach ($pubs as $p) {
                            if (! empty($p)) {
                                $pid = (string) $p;
                                $cleanPubs[] = $pid;
                                $allPubIds[$pid] = true;
                            }
                        }

                        $cleanSems = [];
                        foreach ($sems as $s) {
                            if (! empty($s)) {
                                $cleanSems[] = (string) $s;
                            }
                        }

                        $profilesByUser[$uid] = [
                            'publications' => $cleanPubs,
                            'seminars' => $cleanSems,
                        ];
                    }
                }

                $citationsByPubId = [];
                $distinctPubIds = array_keys($allPubIds);

                if (! empty($distinctPubIds)) {
                    $validObjectIds = [];
                    foreach ($distinctPubIds as $pid) {
                        if (strlen($pid) === 24 && ctype_xdigit($pid)) {
                            $validObjectIds[] = new \MongoDB\BSON\ObjectId($pid);
                        }
                    }

                    if (! empty($validObjectIds)) {
                        $pubDocs = Publication::raw(fn ($col) => $col->find(
                            [
                                '_id' => ['$in' => $validObjectIds],
                                'status' => ['$in' => [1, '1']],
                            ],
                            [
                                'projection' => [
                                    '_id' => 1,
                                    'citations' => 1,
                                ],
                            ]
                        ));

                        foreach ($pubDocs as $pd) {
                            $citationsByPubId[(string) $pd['_id']] = (int) ($pd['citations'] ?? 0);
                        }
                    }
                }

                foreach ($missingOrgIds as $mOid) {
                    $currentUids = array_values(array_keys($currentUsersByOrg[$mOid] ?? []));
                    $alumniRawUids = array_keys($alumniUsersByOrg[$mOid] ?? []);
                    $alumniUids = array_values(array_diff($alumniRawUids, $currentUids));

                    $orgPubIds = [];
                    $orgSeminarIds = [];

                    foreach ($currentUids as $cuid) {
                        if (isset($profilesByUser[$cuid])) {
                            foreach ($profilesByUser[$cuid]['publications'] as $pId) {
                                $orgPubIds[$pId] = true;
                            }
                            foreach ($profilesByUser[$cuid]['seminars'] as $sId) {
                                $orgSeminarIds[$sId] = true;
                            }
                        }
                    }

                    $totalCitations = 0;
                    $validOrgPubIds = [];
                    foreach (array_keys($orgPubIds) as $pId) {
                        if (isset($citationsByPubId[$pId])) {
                            $validOrgPubIds[] = $pId;
                            $totalCitations += $citationsByPubId[$pId];
                        }
                    }

                    $metricData = [
                        'scholars' => count($currentUids),
                        'alumni' => count($alumniUids),
                        'publications' => count($validOrgPubIds),
                        'citations' => $totalCitations,
                        'seminars' => count($orgSeminarIds),
                        'scholar_uids' => $currentUids,
                        'alumni_uids' => $alumniUids,
                        'pub_ids' => $validOrgPubIds,
                    ];

                    Cache::put('org_metrics_v6_'.$mOid, $metricData, 1800);
                    $orgMetricsMap[$mOid] = $metricData;

                    $allUserIds = array_merge($allUserIds, $currentUids, $alumniUids);
                }
            }
        }

        // 6. Batch load user details (avatars, names, profiles) for all scholars & alumni across visible orgs
        $userMap = [];
        $uniqueUserIds = array_values(array_unique(array_filter($allUserIds)));

        if (! empty($uniqueUserIds)) {
            $validUserObjIds = [];
            $validUserStrIds = [];
            foreach ($uniqueUserIds as $uid) {
                $uidStr = (string) $uid;
                $validUserStrIds[] = $uidStr;
                if (strlen($uidStr) === 24 && ctype_xdigit($uidStr)) {
                    $validUserObjIds[] = new \MongoDB\BSON\ObjectId($uidStr);
                }
            }

            $userDocs = User::whereIn('_id', array_merge($validUserObjIds, $validUserStrIds))
                ->whereIn('status', [1, '1'])
                ->select([
                    '_id',
                    'first_name',
                    'last_name',
                    'fullname',
                    'slug',
                    'photo',
                    'email',
                ])
                ->get();

            foreach ($userDocs as $userModel) {
                $idStr = (string) $userModel->_id;
                $slug = ! empty($userModel->slug) ? (string) $userModel->slug : $idStr;
                $profileUrl = url('profile/'.$slug);

                $userData = [
                    'id' => $idStr,
                    'name' => $userModel->name,
                    'avatar' => $userModel->avatar,
                    'url' => $profileUrl,
                ];

                $userMap[$idStr] = $userData;
            }
        }

        $typeLabels = [
            'all' => 'All Organization Types',
            'education' => 'Education & Universities',
            'corporate' => 'Corporate & Industry',
            'research' => 'Research Institutes',
            'government' => 'Government Agencies',
            'hospital' => 'Hospital & Healthcare',
            'non-profit' => 'Non-Profit & NGOs',
            'other' => 'Other Organizations',
        ];

        $hasActiveFilters = ! empty($searchClean) || $this->typeFilter !== 'all' || ! empty($this->countryFilter);

        return [
            'organizations' => $organizations,
            'hasMore' => $hasMore,
            'baseTotalCount' => $baseTotalCount,
            'allCountries' => $allCountries,
            'countryMap' => $countryMap,
            'stateMap' => $stateMap,
            'cityMap' => $cityMap,
            'orgMetricsMap' => $orgMetricsMap,
            'userMap' => $userMap,
            'typeLabels' => $typeLabels,
            'hasActiveFilters' => $hasActiveFilters,
        ];
    }
}; ?>

<div 
    x-data="{
        typeOpen: false,
        countryOpen: false,
        countrySearch: '',
        countries: @js($allCountries),
        get filteredCountries() {
            if (!this.countrySearch || this.countrySearch.trim() === '') {
                return this.countries;
            }
            const q = this.countrySearch.toLowerCase().trim();
            return this.countries.filter(c => c.name.toLowerCase().includes(q) || (c.sortname && c.sortname.toLowerCase().includes(q)));
        }
    }"
    class="min-h-screen pb-12"
>
    <!-- ================= TOP HEADER HERO BANNER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center gap-4">
            <!-- Left Icon Badge -->
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-[#198BEA] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#198BEA]/25">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2L2 7v2h20V7L12 2zM4 11v8h3v-8H4zm6 0v8h4v-8h-4zm7 0v8h3v-8h-3zM2 21v2h20v-2H2z"/>
                </svg>
            </div>

            <!-- Header Content -->
            <div class="space-y-0.5 flex-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                        Academic & Research Institutions
                    </h1>
                    @if($baseTotalCount > 0)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#eaf5ff] text-[#198BEA] dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                            {{ number_format($baseTotalCount) }}+ Institutions
                        </span>
                    @endif
                </div>
                <p class="text-xs sm:text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Explore universities, research centers, academic institutes, and partner organizations worldwide
                </p>
                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed pt-0.5">
                    Connect with global institutions, browse academic affiliations, and foster research collaborations.
                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Collaborate. Innovate. Empower.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================= TWO COLUMN MAIN CONTENT ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- LEFT CONTAINER: INSTITUTIONS LIST & FILTERS -->
            <div class="flex-1 min-w-0 w-full space-y-6">
                
                <!-- UNIFIED FILTER CARD -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200/90 dark:border-zinc-800 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
                    
                    <!-- 1. Search & Sort Controls Row -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <!-- Search Input Box -->
                        <div class="flex-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                wire:model.live.debounce.400ms="search" 
                                placeholder="Search institutions by name, university, city, or country..." 
                                class="w-full h-10.5 pl-10 pr-10 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 hover:border-zinc-300 dark:hover:border-zinc-600 rounded-xl text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:bg-white dark:focus:bg-zinc-900 focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/20 transition"
                            />
                            @if($search !== '')
                                <button 
                                    type="button" 
                                    wire:click="$set('search', '')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer"
                                    title="Clear search"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            @endif
                        </div>

                        <!-- Sort Dropdown -->
                        <div class="sm:w-60 shrink-0 relative">
                            <select 
                                wire:model.live="sortBy"
                                class="w-full h-10.5 pl-3.5 pr-10 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 hover:border-zinc-300 dark:hover:border-zinc-600 rounded-xl text-xs font-semibold text-zinc-800 dark:text-zinc-200 focus:outline-none focus:bg-white dark:focus:bg-zinc-900 focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/20 cursor-pointer transition appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236b7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:8px_8px] bg-[right_12px_center] bg-no-repeat"
                            >
                                <option value="name_asc">Sort: Name (A-Z)</option>
                                <option value="name_desc">Sort: Name (Z-A)</option>
                                <option value="newest">Sort: Recently Added</option>
                                <option value="oldest">Sort: Oldest First</option>
                            </select>
                        </div>
                    </div>

                    <!-- 2. Interactive Filters Bar (Type & Country Dropdowns + Reset) -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-800/80">
                        <div class="flex flex-wrap items-center gap-2.5">
                            
                            <!-- Organization Type Dropdown Trigger -->
                            <div class="relative" @click.outside="typeOpen = false">
                                <button 
                                    type="button" 
                                    @click="typeOpen = !typeOpen; countryOpen = false"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 bg-zinc-50 dark:bg-zinc-800/70 border rounded-xl text-xs font-semibold shadow-xs hover:border-[#198BEA] transition cursor-pointer {{ $typeFilter !== 'all' ? 'border-[#198BEA] text-[#198BEA] bg-sky-50/70 dark:bg-sky-950/40 dark:border-sky-700' : 'border-zinc-200 dark:border-zinc-700/80 text-zinc-700 dark:text-zinc-300' }}"
                                >
                                    <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <span>{{ $typeLabels[$typeFilter] ?? 'Organization Type' }}</span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-150" :class="typeOpen ? 'rotate-180 text-[#198BEA]' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <!-- Type Options Dropdown Menu -->
                                <div 
                                    x-show="typeOpen"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                    class="absolute left-0 top-full mt-1.5 w-64 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl z-50 p-1.5 space-y-0.5"
                                    style="display: none;"
                                >
                                    @foreach($typeLabels as $tKey => $tName)
                                        <button 
                                            type="button" 
                                            @click="$wire.set('typeFilter', '{{ $tKey }}'); typeOpen = false;"
                                            class="w-full flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg text-left transition cursor-pointer {{ $typeFilter === $tKey ? 'bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] font-bold' : 'text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700/60' }}"
                                        >
                                            <span>{{ $tName }}</span>
                                            @if($typeFilter === $tKey)
                                                <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Country Filter Dropdown with Search -->
                            @if(!empty($allCountries))
                                <div class="relative" @click.outside="countryOpen = false">
                                    <button 
                                        type="button" 
                                        @click="countryOpen = !countryOpen; typeOpen = false"
                                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-zinc-50 dark:bg-zinc-800/70 border rounded-xl text-xs font-semibold shadow-xs hover:border-[#198BEA] transition cursor-pointer {{ !empty($countryFilter) ? 'border-[#198BEA] text-[#198BEA] bg-sky-50/70 dark:bg-sky-950/40 dark:border-sky-700' : 'border-zinc-200 dark:border-zinc-700/80 text-zinc-700 dark:text-zinc-300' }}"
                                    >
                                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>
                                            @if(!empty($countryFilter) && isset($countryMap[$countryFilter]))
                                                {{ $countryMap[$countryFilter] }}
                                            @else
                                                All Countries
                                            @endif
                                        </span>
                                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-150" :class="countryOpen ? 'rotate-180 text-[#198BEA]' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <!-- Country Dropdown Menu with Search -->
                                    <div 
                                        x-show="countryOpen"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-100"
                                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                        class="absolute left-0 top-full mt-1.5 w-72 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl z-50 p-2.5 space-y-2"
                                        style="display: none;"
                                    >
                                        <!-- Search input inside dropdown -->
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-zinc-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                            </div>
                                            <input 
                                                type="text" 
                                                x-model="countrySearch" 
                                                placeholder="Search countries..." 
                                                class="w-full h-8 pl-8 pr-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:border-[#198BEA] focus:ring-1 focus:ring-[#198BEA]/20"
                                            />
                                        </div>

                                        <!-- Scrollable Countries List -->
                                        <div class="max-h-56 overflow-y-auto space-y-0.5 pr-1 text-xs">
                                            <button 
                                                type="button" 
                                                @click="$wire.set('countryFilter', ''); countryOpen = false; countrySearch = '';"
                                                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-left transition cursor-pointer {{ empty($countryFilter) ? 'bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] font-bold' : 'text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700/60' }}"
                                            >
                                                <span>All Countries</span>
                                                @if(empty($countryFilter))
                                                    <svg class="w-3.5 h-3.5 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                @endif
                                            </button>

                                            <template x-for="c in filteredCountries" :key="c.id">
                                                <button 
                                                    type="button" 
                                                    @click="$wire.set('countryFilter', c.id); countryOpen = false; countrySearch = '';"
                                                    class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-left transition cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700/60 text-zinc-700 dark:text-zinc-200"
                                                    :class="@js($countryFilter) == c.id ? 'bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] font-bold' : ''"
                                                >
                                                    <span x-text="c.name"></span>
                                                    <template x-if="@js($countryFilter) == c.id">
                                                        <svg class="w-3.5 h-3.5 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </template>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>

                        <!-- Active Filter Clear Action -->
                        @if($hasActiveFilters)
                            <button 
                                type="button" 
                                wire:click="resetFilters"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-red-600 dark:text-red-400 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-950/60 rounded-xl transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>Reset Filters</span>
                            </button>
                        @endif
                    </div>

                    <!-- 3. Active Filters Pill Tags -->
                    @if($hasActiveFilters)
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-dashed border-zinc-200 dark:border-zinc-800 text-xs">
                            @if($search !== '')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800 font-medium">
                                    <span>Keyword: "<strong>{{ $search }}</strong>"</span>
                                    <button type="button" wire:click="$set('search', '')" class="hover:text-red-500 font-bold ml-0.5 cursor-pointer">×</button>
                                </span>
                            @endif

                            @if($typeFilter !== 'all')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-medium">
                                    <span>Type: <strong>{{ $typeLabels[$typeFilter] ?? ucfirst($typeFilter) }}</strong></span>
                                    <button type="button" wire:click="$set('typeFilter', 'all')" class="hover:text-red-500 font-bold ml-0.5 cursor-pointer">×</button>
                                </span>
                            @endif

                            @if(!empty($countryFilter) && isset($countryMap[$countryFilter]))
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-medium">
                                    <span>Country: <strong>{{ $countryMap[$countryFilter] }}</strong></span>
                                    <button type="button" wire:click="$set('countryFilter', '')" class="hover:text-red-500 font-bold ml-0.5 cursor-pointer">×</button>
                                </span>
                            @endif
                        </div>
                    @endif

                </div>

                <!-- Livewire Loading State Banner -->
                <div 
                    wire:loading.flex 
                    wire:target="search, sortBy, typeFilter, countryFilter, resetFilters" 
                    class="items-center justify-center gap-2 py-2.5 px-4 bg-[#198BEA]/10 dark:bg-[#198BEA]/15 border border-[#198BEA]/20 dark:border-[#198BEA]/30 rounded-xl text-xs font-semibold text-[#198BEA] dark:text-sky-400 transition"
                >
                    <svg class="animate-spin w-4 h-4 text-[#198BEA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Filtering institutions...</span>
                </div>

                <!-- 4. Institutions Cards Stream -->
                <div 
                    wire:loading.class="opacity-60 pointer-events-none" 
                    wire:target="search, sortBy, typeFilter, countryFilter, resetFilters"
                    class="space-y-4 transition-opacity duration-150"
                >
                    @forelse ($organizations as $org)
                        @php
                            $orgName = $org->organization_name ?? 'Unnamed Institution';
                            $orgType = strtolower((string)($org->organization_type ?? 'education'));
                            
                            // Country resolution
                            $rawCountry = $org->country ?? '';
                            $countryDisplay = isset($countryMap[$rawCountry]) 
                                ? $countryMap[$rawCountry] 
                                : (!is_numeric($rawCountry) ? (string)$rawCountry : '');
                            
                            // City & State resolution
                            $rawCity = $org->city ?? '';
                            $cityDisplay = isset($cityMap[$rawCity]) 
                                ? $cityMap[$rawCity] 
                                : (!is_numeric($rawCity) ? (string)$rawCity : '');

                            $rawState = $org->state ?? '';
                            $stateDisplay = isset($stateMap[$rawState]) 
                                ? $stateMap[$rawState] 
                                : (!is_numeric($rawState) ? (string)$rawState : '');

                            // Build location string
                            $locationParts = array_filter([$cityDisplay, $stateDisplay, $countryDisplay], fn($p) => !empty($p));
                            $locationText = implode(', ', $locationParts);

                            // Logo source
                            $logoSrc = null;
                            if (!empty($org->logo)) {
                                $logoSrc = str_starts_with($org->logo, 'http') ? $org->logo : asset($org->logo);
                            }

                            // Share URL
                            $shareUrl = !empty($org->slug) 
                                ? url('institution/' . $org->slug) 
                                : url('institution/' . $org->_id);
                        @endphp

                        <div 
                            wire:key="org-{{ $org->_id }}" 
                            class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 hover:border-[#198BEA] dark:hover:border-[#198BEA] rounded-2xl p-5 sm:p-6 shadow-xs hover:shadow-md transition-all duration-200 space-y-4 group"
                        >
                            <div class="flex flex-col sm:flex-row items-start gap-4">
                                <!-- Institution Logo or Avatar Icon -->
                                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0 overflow-hidden shadow-xs">
                                    @if ($logoSrc)
                                        <img 
                                            src="{{ $logoSrc }}" 
                                            alt="{{ $orgName }}" 
                                            class="w-full h-full object-contain p-2"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        />
                                        <div class="w-full h-full hidden items-center justify-center bg-[#198BEA] text-white font-bold text-xl">
                                            {{ mb_substr($orgName, 0, 2) }}
                                        </div>
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-[#eaf5ff] dark:bg-sky-950/40 text-[#198BEA] dark:text-sky-400">
                                            <svg class="w-8 h-8 opacity-85" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2L2 7v2h20V7L12 2zM4 11v8h3v-8H4zm6 0v8h4v-8h-4zm7 0v8h3v-8h-3zM2 21v2h20v-2H2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <!-- Institution Main Content Info -->
                                <div class="flex-1 min-w-0 space-y-2">
                                    <!-- Title & Top Badges Row -->
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors">
                                                <a href="{{ $shareUrl }}" wire:navigate>
                                                    {{ $orgName }}
                                                </a>
                                            </h2>
                                            @if(!empty($org->affiliated_university))
                                                <div class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400 pt-0.5">
                                                    <svg class="w-3.5 h-3.5 text-[#198BEA] shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M12 2 1 7l11 5 9-4.09V15h2V7L12 2z"/>
                                                        <circle cx="12" cy="11.5" r="3"/>
                                                        <path d="M6 19.5c0-2 2.69-3.5 6-3.5s6 1.5 6 3.5V21H6v-1.5z"/>
                                                    </svg>
                                                    <span>Affiliated: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $org->affiliated_university }}</span></span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <!-- Country Badge -->
                                            @if(!empty($countryDisplay))
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/70 dark:border-sky-800">
                                                    <svg class="w-3 h-3 text-[#198BEA] dark:text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>{{ $countryDisplay }}</span>
                                                </span>
                                            @endif

                                            <!-- Organization Type Badge -->
                                            @if($orgType === 'education' || $orgType === 'university' || $orgType === 'college')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                                    Education
                                                </span>
                                            @elseif($orgType === 'corporate' || $orgType === 'company')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                                    Corporate
                                                </span>
                                            @elseif($orgType === 'research')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                    Research
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                                    {{ ucfirst($orgType) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- 5 Key Academic & Research Metrics Grid -->
                                    @php
                                        $metrics = $orgMetricsMap[(string)$org->_id] ?? [
                                            'scholars' => 0,
                                            'alumni' => 0,
                                            'publications' => 0,
                                            'citations' => 0,
                                            'seminars' => 0,
                                            'scholar_uids' => [],
                                            'alumni_uids' => [],
                                        ];

                                        $scholarUsers = array_values(array_filter(array_map(
                                            fn($uid) => $userMap[(string)$uid] ?? null,
                                            $metrics['scholar_uids'] ?? []
                                        )));

                                        $alumniUsers = array_values(array_filter(array_map(
                                            fn($uid) => $userMap[(string)$uid] ?? null,
                                            $metrics['alumni_uids'] ?? []
                                        )));
                                    @endphp

                                    <!-- 1. Key Academic & Research Metrics Inline Row (Matching Image 1) -->
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs sm:text-[13px] font-bold tracking-wide text-[#3B7CA7] dark:text-sky-400 uppercase pt-1">
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

                                    <!-- 2. Scholars & Alumni Lists (Matching Image 2 with Collapsible +X More / Show Less) -->
                                    @if(!empty($scholarUsers) || !empty($alumniUsers))
                                        <div 
                                            x-data="{ openScholars: false, openAlumni: false }" 
                                            class="space-y-2.5 pt-2 border-t border-dashed border-zinc-100 dark:border-zinc-800 text-xs sm:text-sm"
                                        >
                                            @if(!empty($scholarUsers))
                                                @php
                                                    $scholarCount = count($scholarUsers);
                                                    $hasMoreScholars = $scholarCount > 2;
                                                @endphp
                                                <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                                                    <span class="font-bold text-[#3B7CA7] dark:text-sky-400 uppercase tracking-wide shrink-0">
                                                        SCHOLARS :
                                                    </span>
                                                    @foreach($scholarUsers as $idx => $scholar)
                                                        <div 
                                                            @if($idx >= 2) 
                                                                x-show="openScholars" 
                                                                x-cloak 
                                                            @endif 
                                                            class="inline-flex items-center"
                                                        >
                                                            <a 
                                                                href="{{ $scholar['url'] }}" 
                                                                wire:navigate 
                                                                class="inline-flex items-center gap-2 group/user hover:opacity-90 transition cursor-pointer"
                                                                title="{{ $scholar['name'] }}"
                                                            >
                                                                <img 
                                                                    src="{{ $scholar['avatar'] }}" 
                                                                    alt="{{ $scholar['name'] }}" 
                                                                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover shrink-0 border border-zinc-200/90 dark:border-zinc-700 shadow-2xs" 
                                                                    loading="lazy"
                                                                />
                                                                <span class="font-semibold text-zinc-800 dark:text-zinc-200 group-hover/user:text-[#198BEA] dark:group-hover/user:text-sky-400 transition-colors">
                                                                    {{ $scholar['name'] }}
                                                                </span>
                                                            </a>
                                                        </div>
                                                    @endforeach

                                                    @if($hasMoreScholars)
                                                        <button 
                                                            type="button" 
                                                            @click="openScholars = !openScholars" 
                                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900 border border-sky-200/80 dark:border-sky-800 transition cursor-pointer shadow-2xs shrink-0"
                                                        >
                                                            <span x-show="!openScholars">+{{ $scholarCount - 2 }} More</span>
                                                            <span x-show="openScholars" x-cloak>Show Less</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif

                                            @if(!empty($alumniUsers))
                                                @php
                                                    $alumniCount = count($alumniUsers);
                                                    $hasMoreAlumni = $alumniCount > 2;
                                                @endphp
                                                <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                                                    <span class="font-bold text-[#3B7CA7] dark:text-sky-400 uppercase tracking-wide shrink-0">
                                                        ALUMNUS :
                                                    </span>
                                                    @foreach($alumniUsers as $idx => $alumnus)
                                                        <div 
                                                            @if($idx >= 2) 
                                                                x-show="openAlumni" 
                                                                x-cloak 
                                                            @endif 
                                                            class="inline-flex items-center"
                                                        >
                                                            <a 
                                                                href="{{ $alumnus['url'] }}" 
                                                                wire:navigate 
                                                                class="inline-flex items-center gap-2 group/user hover:opacity-90 transition cursor-pointer"
                                                                title="{{ $alumnus['name'] }}"
                                                            >
                                                                <img 
                                                                    src="{{ $alumnus['avatar'] }}" 
                                                                    alt="{{ $alumnus['name'] }}" 
                                                                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover shrink-0 border border-zinc-200/90 dark:border-zinc-700 shadow-2xs" 
                                                                    loading="lazy"
                                                                />
                                                                <span class="font-semibold text-zinc-800 dark:text-zinc-200 group-hover/user:text-[#198BEA] dark:group-hover/user:text-sky-400 transition-colors">
                                                                    {{ $alumnus['name'] }}
                                                                </span>
                                                            </a>
                                                        </div>
                                                    @endforeach

                                                    @if($hasMoreAlumni)
                                                        <button 
                                                            type="button" 
                                                            @click="openAlumni = !openAlumni" 
                                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900 border border-sky-200/80 dark:border-sky-800 transition cursor-pointer shadow-2xs shrink-0"
                                                        >
                                                            <span x-show="!openAlumni">+{{ $alumniCount - 2 }} More</span>
                                                            <span x-show="openAlumni" x-cloak>Show Less</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Card Footer: View Details (Left) & Share Button (Right) -->
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                <div>
                                    <!-- View Details Button -->
                                    @php
                                        $detailUrl = !empty($org->slug) 
                                            ? url('institution/' . $org->slug) 
                                            : url('institution/' . $org->_id);
                                    @endphp

                                    <a 
                                        href="{{ $detailUrl }}" 
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs font-semibold rounded-lg shadow-xs hover:shadow transition-all cursor-pointer"
                                    >
                                        <span>View Details</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                    </a>
                                </div>

                                <!-- Share Button (Dispatches global modal) -->
                                <button 
                                    type="button" 
                                    @click="$dispatch('open-share-modal', { url: @js($shareUrl), title: @js($orgName), type: 'institution', header: 'Share Institution', subtitle: 'Share this institution across academic and social networks' })"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-500 hover:text-[#198BEA] dark:text-zinc-400 dark:hover:text-sky-400 transition cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                    </svg>
                                    <span>Share</span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <!-- Empty State -->
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-10 text-center shadow-xs space-y-3">
                            <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                                No Institutions Found
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto">
                                We couldn't find any institutions matching your search criteria or filters. Try adjusting your query or resetting filters.
                            </p>
                            @if($hasActiveFilters)
                                <div class="pt-2">
                                    <button 
                                        type="button" 
                                        wire:click="resetFilters"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-lg shadow-sm transition cursor-pointer"
                                    >
                                        <span>Reset all filters</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforelse
                </div>

                <!-- 5. Load More Button -->
                @if ($hasMore)
                    <div class="flex justify-center pt-6 pb-4">
                        <button 
                            type="button" 
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 px-8 py-2.5 rounded-full bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] disabled:opacity-70 text-white text-sm font-semibold shadow-md shadow-[#198BEA]/25 hover:shadow-lg hover:shadow-[#198BEA]/30 active:scale-[0.98] transition-all cursor-pointer"
                        >
                            <svg wire:loading wire:target="loadMore" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="loadMore">Load More Institutions</span>
                            <span wire:loading wire:target="loadMore">Loading more institutions...</span>
                        </button>
                    </div>
                @endif

            </div>

            <!-- RIGHT CONTAINER: REUSABLE SIDEBAR -->
            <x-article.sidebar />

        </div>
    </div>
</div>
