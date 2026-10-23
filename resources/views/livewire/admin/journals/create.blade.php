<?php

use App\Helpers\TimezoneHelper;
use App\Livewire\Admin\Journals\HandlesJournalForm;
use App\Models\Country;
use App\Models\JournalRole;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Add Journal - Admin')] class extends Component {
    use WithFileUploads;
    use HandlesJournalForm;

    // 1. Publisher & Core Info
    #[Validate('nullable|string')]
    public string $user_id = '';

    #[Validate('required|string|max:255')]
    public string $organization_name = '';

    #[Validate('required|string|max:500')]
    public string $journal_title = '';

    #[Validate('nullable|string|max:255')]
    public string $journal_short_name = '';

    #[Validate('nullable|string|max:100')]
    public string $conference_charges = '';

    #[Validate('required|url|max:500')]
    public string $journal_webiste_url = '';

    #[Validate('required|integer|min:1800|max:2099')]
    public ?int $first_publication_year = null;

    #[Validate('nullable|image|mimes:jpg,jpeg,png|max:500')]
    public mixed $journal_photo = null;

    // 2. ISSN & Impact Details
    #[Validate('nullable|string|max:50')]
    public string $e_issn = '';

    #[Validate('nullable|string|max:50')]
    public string $p_issn = '';

    #[Validate('nullable|string')]
    public string $issn_assignee_country = '';

    #[Validate('nullable|string|max:50')]
    public string $journal_impact_factor = '';

    #[Validate('nullable|string|max:255')]
    public string $impact_factor_assignee_agency = '';

    #[Validate('nullable|string|max:100')]
    public string $issue_frequency = 'Quarterly';

    // 3. Discipline & Scope
    public array $languages = [];

    public array $journal_subjects = [];

    #[Validate('required|string')]
    public string $journal_description = '';

    // 4. Contact & Editorial Team
    #[Validate('required|string|max:255')]
    public string $contact_name = '';

    #[Validate('required|email|max:255')]
    public string $contact_email = '';

    public string $country_code = '+91';

    #[Validate('required|string|max:50')]
    public string $mobile = '';

    // 5. Location
    #[Validate('required|string|max:500')]
    public string $address = '';

    #[Validate('required|string')]
    public string $country = '';

    #[Validate('nullable|string')]
    public string $state = '';

    #[Validate('nullable|string')]
    public string $city = '';

    #[Validate('required|string|max:30')]
    public string $zipcode = '';

    // 6. Compliance & Licensing Questions (Yes/No as "1"/"0")
    public string $JournalOpenAccess = '1';
    public string $COPEguidelines = '1';
    public string $restrictions = '1';
    public string $followpeer = '';
    public bool $EditorialReview = false;
    public bool $PeerReview = false;
    public bool $AnonymousPeerReview = false;
    public bool $DoubleAnonymousPeerReview = false;
    public bool $PostPublicationPeerReview = false;
    public bool $OpenPeerReview = false;
    public bool $other = false;
    public string $otherPeer = '';
    public string $plagiarism = '1';
    public string $publicationPlatform = 'Open Journal Systems (OJS). The Public Knowledge Project';
    public string $feesAssociatedWithPublishing = '1';
    public string $preservation = '1';
    public string $DOI = '1';
    public string $doi = '';

    // 7. Indexing Agencies (20 target agencies)
    public array $indexing = [];

    // 8. Status
    #[Validate('required|in:0,1')]
    public string $status = '1';

    public ?string $createdSlug = null;
    public ?string $createdTitle = null;

    public function mount(): void
    {
        $this->editorial_members = [];

        $roles = JournalRole::getCachedActive();
        if (! empty($roles)) {
            $this->selectedRole = (string) ($roles[0]['id'] ?? $roles[0]['name'] ?? 'Editor-in-Chief');
        }

        foreach ($this->availableAgencies() as $agency) {
            $this->indexing[$agency['key']] = [
                'checked' => false,
                'url' => '',
            ];
        }

        if (empty($this->country)) {
            $defaultCountryId = TimezoneHelper::detectDefaultCountryId();
            if ($defaultCountryId) {
                $this->country = (string) $defaultCountryId;
                if (empty($this->issn_assignee_country)) {
                    $this->issn_assignee_country = (string) $defaultCountryId;
                }
            }
        }
    }

    public function autoDetectCountry(?string $clientTimezone = null): void
    {
        if (empty($this->country) && ! empty($clientTimezone)) {
            $countryId = TimezoneHelper::detectDefaultCountryId($clientTimezone);
            if ($countryId) {
                $this->country = (string) $countryId;
                if (empty($this->issn_assignee_country)) {
                    $this->issn_assignee_country = (string) $countryId;
                }
            }
        }

        if (Auth::check() && ! empty($clientTimezone)) {
            $user = Auth::user();
            if ($user instanceof User && (empty($user->timezone) || empty($user->country))) {
                TimezoneHelper::updateUserLocation($user, $clientTimezone);
            }
        }
    }



    public function boot(): void
    {
        $this->withValidator(function (\Illuminate\Validation\Validator $validator) {
            $validator->setCustomMessages($this->journalValidationMessages());
            $this->validateJournalCrossFields($validator);
        });
    }

    public function save(): void
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-first-error');
            throw $e;
        }

        try {
            $verificationResult = $this->performStrictVerificationOnSave($this->e_issn, $this->p_issn, $this->journal_title);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-first-error');
            throw $e;
        }

        $photoPath = null;
        if ($this->journal_photo) {
            $ext = method_exists($this->journal_photo, 'getClientOriginalExtension')
                ? ($this->journal_photo->getClientOriginalExtension() ?: 'jpg')
                : 'jpg';
            $filename = uniqid().'.'.$ext;
            $dir = public_path('uploads/journalphotos');
            File::ensureDirectoryExists($dir);
            $target = $dir.DIRECTORY_SEPARATOR.$filename;

            $sourcePath = method_exists($this->journal_photo, 'getRealPath')
                ? $this->journal_photo->getRealPath()
                : (is_string($this->journal_photo) ? $this->journal_photo : null);

            if ($sourcePath && file_exists($sourcePath)) {
                File::copy($sourcePath, $target);
                $photoPath = 'uploads/journalphotos/'.$filename;
            } elseif (method_exists($this->journal_photo, 'storeAs')) {
                try {
                    $this->journal_photo->storeAs('uploads/journalphotos', $filename, 'public_uploads');
                    $photoPath = 'uploads/journalphotos/'.$filename;
                } catch (\Throwable $e) {
                    // Fallback
                }
            }
        }

        $journal = new ReviewerJournal();
        $journal->user_id = ! empty($this->user_id) ? (string) $this->user_id : null;
        $journal->organization_name = trim($this->organization_name);
        $journal->journal_title = trim($this->journal_title);
        $journal->journal_short_name = trim($this->journal_short_name);
        $charges = trim($this->conference_charges);
        if ($charges !== '') {
            $journal->conference_charges = $charges;
            $journal->conference_allowed = '1';
        } else {
            $journal->conference_charges = null;
            $journal->conference_allowed = '0';
        }
        $journal->journal_webiste_url = trim($this->journal_webiste_url);
        $journal->first_publication_year = $this->first_publication_year;
        if ($photoPath) {
            $journal->journal_photo = $photoPath;
        }

        $journal->e_issn = \App\Models\ReviewerJournal::formatIssn($this->e_issn);
        $journal->p_issn = \App\Models\ReviewerJournal::formatIssn($this->p_issn);
        $journal->issn = $journal->e_issn ?: $journal->p_issn;
        $journal->issn_assignee_country = (string) $this->issn_assignee_country;
        $journal->journal_impact_factor = trim($this->journal_impact_factor);
        $journal->impact_factor_assignee_agency = trim($this->impact_factor_assignee_agency);
        $journal->issue_frequency = trim($this->issue_frequency);

        $journal->languages = array_values(array_filter($this->languages));
        $journal->journal_subjects = array_values(array_filter($this->journal_subjects));
        $journal->journal_description = trim($this->journal_description);

        $journal->contact_name = trim($this->contact_name);
        $journal->contact_email = trim($this->contact_email);
        $rawMobile = trim($this->mobile);
        $countryCode = trim($this->country_code ?: '+91');
        if (! str_starts_with($countryCode, '+') && $countryCode !== '') {
            $countryCode = '+'.$countryCode;
        }

        if ($countryCode !== '' && str_starts_with($rawMobile, $countryCode)) {
            $rawMobile = substr($rawMobile, strlen($countryCode));
        } elseif (str_starts_with($rawMobile, '+')) {
            if (preg_match('/^(\+\d{1,4})\s*(.*)$/', $rawMobile, $matches)) {
                $countryCode = $matches[1];
                $rawMobile = $matches[2];
            }
        }

        $cleanedMobile = preg_replace('/[^\d]/', '', $rawMobile);

        $journal->country_code = $countryCode;
        $journal->mobile = $cleanedMobile ?: $rawMobile;

        // Editorial Board: Do not save name and photo into registered_co_author (fetched dynamically via user_id)
        $journal->registered_co_author = $this->formatRegisteredCoAuthors();

        // Location
        $journal->address = trim($this->address);
        $journal->country = (string) $this->country;
        $journal->state = (string) $this->state;
        $journal->city = (string) $this->city;
        $journal->zipcode = trim($this->zipcode);

        // Compliance
        $journal->JournalOpenAccess = (string) $this->JournalOpenAccess;
        $journal->COPEguidelines = (string) $this->COPEguidelines;
        $journal->restrictions = (string) $this->restrictions;
        $journal->followpeer = (string) $this->followpeer;
        $journal->EditorialReview = ($this->followpeer === '1' && $this->EditorialReview) ? 'on' : null;
        $journal->PeerReview = ($this->followpeer === '1' && $this->PeerReview) ? 'on' : null;
        $journal->AnonymousPeerReview = ($this->followpeer === '1' && $this->AnonymousPeerReview) ? 'on' : null;
        $journal->DoubleAnonymousPeerReview = ($this->followpeer === '1' && $this->DoubleAnonymousPeerReview) ? 'on' : null;
        $journal->PostPublicationPeerReview = ($this->followpeer === '1' && $this->PostPublicationPeerReview) ? 'on' : null;
        $journal->OpenPeerReview = ($this->followpeer === '1' && $this->OpenPeerReview) ? 'on' : null;
        $journal->other = ($this->followpeer === '1' && $this->other) ? 'on' : null;
        $journal->otherPeer = ($this->followpeer === '1' && $this->other) ? trim($this->otherPeer) : null;
        $journal->plagiarism = (string) $this->plagiarism;
        $journal->publicationPlatform = trim($this->publicationPlatform);
        $journal->feesAssociatedWithPublishing = (string) $this->feesAssociatedWithPublishing;
        $journal->preservation = (string) $this->preservation;
        $journal->DOI = (string) $this->DOI;
        $journal->doi = ($this->DOI === '1') ? trim($this->doi) : null;

        // Indexing Agencies
        foreach ($this->availableAgencies() as $ag) {
            $k = $ag['key'];
            $isChecked = ! empty($this->indexing[$k]['checked']);
            $url = trim($this->indexing[$k]['url'] ?? '');

            $journal->{$k.'_checkbox'} = $isChecked;
            if ($isChecked && $url !== '') {
                $journal->{$k} = $url;
            } else {
                $journal->{$k} = null;
            }
        }

        $journal->is_verified = true;
        $journal->verification_status = 'verified';
        $journal->verification_source = $verificationResult?->source ?? 'auto';
        $journal->verified_at = now();
        $journal->verification_details = $verificationResult?->toArray() ?? [];

        $journal->status = in_array((string) $this->status, ['0', '1'], true) ? (int) $this->status : 1;
        $journal->save();

        session()->flash('message', "Journal '{$journal->journal_title}' has been successfully created!");

        $this->redirect(route('admin.journals.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'publishers' => User::getInitialPublishers(20, $this->user_id),
            'journalRoles' => JournalRole::getCachedActive(),
            'data' => [
                'journal_role' => JournalRole::getCachedActive(),
            ],
            'countries' => Country::getCachedAll(),
            'publicationYears' => ReviewerJournal::getPublicationYears(),
            'defaultLanguages' => ReviewerJournal::getDefaultLanguages(),
            'defaultSubjects' => ReviewerJournal::getDefaultDisciplines(),
            'states' => $this->getStatesList(),
            'cities' => $this->getCitiesList(),
            'agencies' => $this->availableAgencies(),
        ];
    }
};
?>

<div 
    x-data="{
        // Modal 1: Existing Journal Selected from Dropdown
        showExistingModal: false,
        selectedExistingJournal: null,

        openExistingJournalModal(item) {
            this.selectedExistingJournal = item;
            this.showExistingModal = true;
        },

        goToExistingJournal() {
            if (this.selectedExistingJournal && this.selectedExistingJournal.journal_title) {
                window.location.href = '{{ route('admin.journals.index') }}?search=' + encodeURIComponent(this.selectedExistingJournal.journal_title);
            }
        },

        cancelExistingJournal() {
            this.showExistingModal = false;
            this.selectedExistingJournal = null;
            $wire.set('journal_title', '');
            window.dispatchEvent(new CustomEvent('clear-journal-title'));
        },

        // Modal 2: Existing Journal Found by ISSN
        showIssnFoundModal: false,
        foundIssnJournal: null,
        checkedIssns: {},
        dismissedIssns: {},

        formatIssnValue(event, field) {
            if (typeof formatIssn === 'function') {
                formatIssn(event, field, $wire);
            } else {
                let raw = (event.target.value || '').toUpperCase();
                let clean = '';
                for (let i = 0; i < raw.length && clean.length < 8; i++) {
                    let ch = raw[i];
                    if (clean.length < 7) {
                        if (/[0-9]/.test(ch)) clean += ch;
                    } else {
                        if (/[0-9X]/.test(ch)) clean += ch;
                    }
                }
                if (clean.length > 4) {
                    clean = clean.substring(0, 4) + '-' + clean.substring(4);
                }
                event.target.value = clean;
                $wire.set(field, clean);
            }
        },

        async checkIssnInput(value, fieldType) {
            if (!value) return;
            const clean = value.trim().toUpperCase();
            const issnPattern = /^\d{4}-\d{3}[\dX]$/;
            if (!issnPattern.test(clean)) {
                return;
            }

            if (this.checkedIssns[clean] || this.dismissedIssns[clean]) {
                return;
            }
            this.checkedIssns[clean] = true;

            try {
                const result = await $wire.checkIssn(clean);
                if (result && result.journal_title) {
                    this.foundIssnJournal = result;
                    this.showIssnFoundModal = true;
                }
            } catch (e) {
                console.error('Error checking ISSN:', e);
            }
        },

        confirmUseIssnJournal() {
            if (this.foundIssnJournal) {
                $wire.selectExistingJournal(this.foundIssnJournal);
                window.dispatchEvent(new CustomEvent('journal-auto-filled', { detail: this.foundIssnJournal }));
            }
            this.showIssnFoundModal = false;
            this.foundIssnJournal = null;
        },

        dismissIssnPrompt() {
            if (this.foundIssnJournal) {
                const eIssn = (this.foundIssnJournal.e_issn || '').toUpperCase();
                const pIssn = (this.foundIssnJournal.p_issn || '').toUpperCase();
                if (eIssn) this.dismissedIssns[eIssn] = true;
                if (pIssn) this.dismissedIssns[pIssn] = true;
            }
            this.showIssnFoundModal = false;
            this.foundIssnJournal = null;
        }
    }" 
    x-init="
        if (!$wire.country) {
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (tz) {
                    $wire.autoDetectCountry(tz);
                }
            } catch (e) {}
        }
    "
    class="space-y-6 w-full"
>
    <!-- BREADCRUMBS & TOP BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-brand transition flex items-center gap-1 font-medium">
                <flux:icon name="home" class="size-3.5" />
                <span>Admin</span>
            </a>
            <span>/</span>
            <a href="{{ route('admin.journals.index') }}" wire:navigate class="hover:text-brand transition font-medium">
                Journals
            </a>
            <span>/</span>
            <span class="font-bold text-zinc-900 dark:text-white">Add Journal</span>
        </div>

        <a 
            href="{{ route('admin.journals.index') }}" 
            wire:navigate 
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition self-start sm:self-auto"
        >
            <flux:icon name="arrow-left" class="size-3.5" />
            <span>All Journals</span>
        </a>
    </div>

    <!-- FLASH SUCCESS MESSAGE -->
    @if(session()->has('message'))
        <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs font-semibold shadow-xs">
            <div class="flex items-center gap-2.5">
                <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <div class="flex items-center gap-3">
                @if($createdSlug)
                    <a href="{{ route('journal.show', $createdSlug) }}" target="_blank" class="underline text-emerald-700 dark:text-emerald-300 font-bold hover:text-emerald-900 inline-flex items-center gap-1">
                        <span>View Public Page</span>
                        <flux:icon name="arrow-top-right-on-square" class="size-3" />
                    </a>
                @endif
                <a href="{{ route('admin.journals.index') }}" wire:navigate class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition">
                    View in List &rarr;
                </a>
            </div>
        </div>
    @endif

    <!-- MAIN FORM -->
    <form 
        wire:submit="save" 
        x-data="{
            scrollToFirstError() {
                this.$nextTick(() => {
                    setTimeout(() => {
                        const firstError = document.querySelector('.error-message, .border-rose-400');
                        if (firstError) {
                            const wrapper = firstError.closest('[data-field-wrapper]') || firstError.parentElement;
                            const focusable = wrapper ? wrapper.querySelector('input:not([type=\'hidden\']), textarea, select, [role=\'button\'][tabindex=\'0\']') : null;
                            const target = focusable || wrapper || firstError;
                            
                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            if (focusable && typeof focusable.focus === 'function') {
                                setTimeout(() => {
                                    try { focusable.focus(); } catch (e) {}
                                }, 350);
                            }
                        }
                    }, 120);
                });
            }
        }"
        x-on:scroll-to-first-error.window="scrollToFirstError()"
        class="space-y-6"
    >
        @if ($errors->any())
            <div x-init="scrollToFirstError()" class="hidden"></div>
        @endif

        <!-- 1. PUBLISHER & CORE IDENTITY -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3 rounded-t-2xl">
                <div class="size-10 rounded-xl bg-brand/10 border border-brand/20 text-brand flex items-center justify-center shrink-0">
                    <flux:icon name="book-open" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">1. Journal Core Details & Publisher</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Basic metadata, publisher assignment, and identity.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- Select Publisher & Publisher Name -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Select Publisher <span class="text-zinc-400 font-normal lowercase">(Optional)</span>
                        </label>
                        <x-searchable-select 
                            wire:model="user_id" 
                            :options="$publishers" 
                            placeholder="Select Publisher" 
                            search-placeholder="Search publisher by name or email..."
                            no-results-text="No result Found"
                            search-method="searchPublishers"
                            :has-error="$errors->has('user_id')"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Assign registered publisher account (filtered by status & publisher_status).</p>
                        @error('user_id') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Publisher Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="organization_name" 
                            placeholder="e.g. IJEDR Publishing or Oxford University Press"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('organization_name') border-rose-400 @enderror"
                        />
                        @error('organization_name') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Journal Full Name & Short Name -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div 
                        data-field-wrapper
                        class="md:col-span-2 relative"
                        x-data="{
                            open: false,
                            results: [],
                            loading: false,
                            lastQuery: '',
                            title: @entangle('journal_title'),
                            cache: {},
                            controller: null,

                            init() {
                                window.addEventListener('clear-journal-title', () => {
                                    this.title = '';
                                    this.lastQuery = '';
                                    this.results = [];
                                    this.open = false;
                                });
                                window.addEventListener('journal-auto-filled', (e) => {
                                    if (e.detail && e.detail.journal_title) {
                                        this.title = e.detail.journal_title;
                                        this.lastQuery = e.detail.journal_title;
                                        this.open = false;
                                    }
                                });
                            },

                            async search() {
                                const q = (this.title || '').trim();
                                if (q.length < 2) {
                                    this.results = [];
                                    this.open = false;
                                    this.loading = false;
                                    if (this.controller) {
                                        this.controller.abort();
                                    }
                                    return;
                                }
                                this.open = true;
                                if (q === this.lastQuery && this.results.length > 0) {
                                    return;
                                }
                                if (this.cache[q]) {
                                    this.results = this.cache[q];
                                    this.loading = false;
                                    this.lastQuery = q;
                                    return;
                                }
                                if (this.controller) {
                                    this.controller.abort();
                                }
                                this.controller = new AbortController();
                                this.loading = true;
                                this.lastQuery = q;
                                try {
                                    const resp = await fetch('{{ route('search-journals') }}?search=' + encodeURIComponent(q), {
                                        signal: this.controller.signal,
                                        headers: { 'Accept': 'application/json' }
                                    });
                                    if (resp.ok) {
                                        const data = await resp.json();
                                        const list = Array.isArray(data.journals) ? data.journals : [];
                                        this.cache[q] = list;
                                        if ((this.title || '').trim() === q) {
                                            this.results = list;
                                        }
                                    }
                                } catch (e) {
                                    if (e.name !== 'AbortError') {
                                        console.error('Journal search error:', e);
                                    }
                                } finally {
                                    if ((this.title || '').trim() === q) {
                                        this.loading = false;
                                    }
                                }
                            },

                            select(item) {
                                this.open = false;
                                openExistingJournalModal(item);
                            },

                            selectNew() {
                                if (this.hasExactMatch()) {
                                    const match = this.results.find(item => (item.journal_title || '').trim().toLowerCase() === (this.title || '').trim().toLowerCase());
                                    if (match) {
                                        this.open = false;
                                        openExistingJournalModal(match);
                                        return;
                                    }
                                }
                                this.open = false;
                                this.lastQuery = (this.title || '').trim();
                            },

                            hasExactMatch() {
                                const q = (this.title || '').trim().toLowerCase();
                                if (!q || !Array.isArray(this.results)) return false;
                                return this.results.some(item => (item.journal_title || '').trim().toLowerCase() === q);
                            }
                        }"
                        @click.outside="open = false"
                    >
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Journal Full Name <span class="text-rose-500">*</span>
                            </label>
                            <span x-show="loading" class="text-[11px] text-brand font-medium animate-pulse" style="display: none;">Searching existing...</span>
                        </div>
                        <div class="relative">
                            <input 
                                type="text" 
                                x-model="title"
                                @input.debounce.350ms="search()"
                                @focus="if ((title || '').trim().length >= 2) open = true"
                                @keydown.escape="open = false"
                                @keydown.enter.prevent="selectNew()"
                                placeholder="Type or search journal name (e.g. International Journal of Engineering...)"
                                class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('journal_title') border-rose-400 @enderror"
                            />
                            <div x-show="loading" class="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center pointer-events-none" style="display: none;">
                                <svg class="animate-spin size-4 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </div>
                        </div>
                        @error('journal_title') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror

                        <!-- Suggestions & New Journal Dropdown -->
                        <div 
                            x-show="open && (title || '').trim().length >= 2" 
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 right-0 z-50 mt-1 max-h-80 overflow-y-auto rounded-xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 shadow-xl py-1 text-xs"
                            style="display: none;"
                        >
                            <!-- When matching existing journals exist -->
                            <template x-if="results.length > 0">
                                <div>
                                    <div class="px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-400 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                                        <span>Existing registered journals (click to view / select)</span>
                                        <span x-text="results.length + ' found'"></span>
                                    </div>

                                    <template x-for="item in results" :key="item.id">
                                        <div 
                                            @click="select(item)"
                                            class="px-3.5 py-2.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 cursor-pointer flex items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm truncate" x-text="item.journal_title"></div>
                                                <div class="flex items-center gap-2 mt-0.5 text-zinc-500 dark:text-zinc-400">
                                                    <span x-show="item.journal_short_name" class="font-mono text-[11px] text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded" x-text="item.journal_short_name"></span>
                                                    <span x-show="item.e_issn" class="text-[11px]" x-text="'e-ISSN: ' + item.e_issn"></span>
                                                    <span x-show="item.p_issn" class="text-[11px]" x-text="'p-ISSN: ' + item.p_issn"></span>
                                                </div>
                                            </div>

                                            <div class="shrink-0 flex items-center gap-1.5">
                                                <span 
                                                    x-show="item.status === 1"
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                                                >
                                                    Active
                                                </span>
                                                <span 
                                                    x-show="item.status !== 1"
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                                                >
                                                    Inactive
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Option to Register as New Journal ONLY when NO matching journals exist -->
                            <div 
                                x-show="results.length === 0 && !loading"
                                @click="selectNew()"
                                class="px-3.5 py-2.5 bg-brand/5 hover:bg-brand/10 dark:bg-brand/10 dark:hover:bg-brand/20 cursor-pointer flex items-center justify-between gap-3 transition"
                            >
                                <div class="flex items-center gap-2 truncate">
                                    <flux:icon name="plus-circle" class="size-4 shrink-0 text-brand" />
                                    <span class="truncate text-xs text-zinc-800 dark:text-zinc-200">
                                        No existing journal matched. Add as new: <strong class="text-brand font-bold underline" x-text="title"></strong>
                                    </span>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/15 text-brand uppercase tracking-wider">
                                    Add New
                                </span>
                            </div>
                        </div>
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Journal Short Name
                        </label>
                        <input 
                            type="text" 
                            wire:model="journal_short_name" 
                            placeholder="e.g. IJEDR"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                        />
                    </div>
                </div>

                <!-- Website URL, Conference Charges, Year of First Publication -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Journal Website URL <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="url" 
                            wire:model="journal_webiste_url" 
                            placeholder="https://www.ijedr.org"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('journal_webiste_url') border-rose-400 @enderror"
                        />
                        @error('journal_webiste_url') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Year of First Publication <span class="text-rose-500">*</span>
                        </label>
                        <x-searchable-select 
                            wire:model="first_publication_year" 
                            :options="$publicationYears" 
                            placeholder="Select Year" 
                            search-placeholder="Search year (e.g. 2024, 1998)..."
                            no-results-text="No year found"
                            :has-error="$errors->has('first_publication_year')"
                        />
                        @error('first_publication_year') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Conference Charges <span class="text-zinc-400 font-normal lowercase">(Optional)</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="conference_charges" 
                            placeholder="e.g. Free or Charge (Optional)"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                        />
                    </div>
                </div>

                <!-- Journal Photo Upload -->
                <div data-field-wrapper>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Upload Your Journal Photo <span class="text-zinc-400 font-normal">(Less than 500kb, JPG, JPEG, PNG)</span>
                    </label>
                    <div class="flex items-start gap-4 p-4 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/30">
                        @if ($journal_photo)
                            <div class="relative size-20 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-white dark:bg-zinc-800 p-1 shrink-0">
                                <img src="{{ $journal_photo->temporaryUrl() }}" alt="Journal Photo" class="w-full h-full object-contain rounded-lg" />
                                <button type="button" wire:click="$set('journal_photo', null)" class="absolute top-1 right-1 size-5 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs cursor-pointer">
                                    &times;
                                </button>
                            </div>
                        @else
                            <div class="size-16 rounded-xl border border-zinc-300 dark:border-zinc-700 flex items-center justify-center text-zinc-400 shrink-0 bg-white dark:bg-zinc-800">
                                <flux:icon name="photo" class="size-6" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <input 
                                type="file" 
                                wire:model="journal_photo" 
                                accept="image/jpeg,image/png,image/jpg"
                                class="block w-full text-xs text-zinc-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand/10 file:text-brand hover:file:bg-brand/20 transition cursor-pointer"
                            />
                            <p class="text-[11px] text-zinc-400 mt-1">Recommended square or portrait aspect ratio for clean journal cover rendering.</p>
                            @error('journal_photo') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ISSN, IMPACT FACTOR & PUBLICATION FREQUENCY -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3 rounded-t-2xl">
                <div class="size-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <flux:icon name="identification" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">2. ISSN, Impact Factor & Publication Frequency</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">ISSN registry numbers, impact evaluations, and scheduling.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- Official Journal Registry Verification Bar -->
                <div class="p-4 rounded-xl border border-sky-100 dark:border-sky-950/60 bg-gradient-to-r from-sky-50/70 via-indigo-50/40 to-transparent dark:from-sky-950/20 dark:via-indigo-950/10 dark:to-transparent flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <flux:icon name="check-badge" class="size-4" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">Official Journal Verification</span>
                                @if($isJournalVerifiedOnline === true)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">
                                        <flux:icon name="check" class="size-3" /> Verified ({{ $verifiedSourceLabel ?? 'Official Registry' }})
                                    </span>
                                @elseif($isJournalVerifiedOnline === false)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-300 dark:border-rose-800">
                                        <flux:icon name="x-mark" class="size-3" /> Unverified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                        Pending Verification
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Check official existence against Crossref, OpenAlex, DOAJ, or ISSN Portal.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <select 
                            wire:model.live="verificationSource"
                            class="px-3 py-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 focus:outline-none focus:ring-1 focus:ring-brand"
                            title="Select Registry Provider"
                        >
                            <option value="auto">Auto (Any Official Registry)</option>
                            <option value="crossref">Crossref API</option>
                            <option value="openalex">OpenAlex API</option>
                            <option value="doaj">DOAJ API</option>
                            <option value="issn_portal">Official ISSN Portal</option>
                        </select>

                        <button 
                            type="button"
                            wire:click="verifyJournalOnline"
                            wire:loading.attr="disabled"
                            wire:target="verifyJournalOnline"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-sky-600 hover:bg-sky-500 active:bg-sky-700 shadow-xs transition-all disabled:opacity-50 cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="verifyJournalOnline" class="flex items-center gap-1.5">
                                <flux:icon name="magnifying-glass" class="size-3.5" />
                                Verify Journal
                            </span>
                            <span wire:loading wire:target="verifyJournalOnline" class="flex items-center gap-1.5">
                                <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Verifying...
                            </span>
                        </button>
                    </div>
                </div>

                @if($isJournalVerifiedOnline === true && !empty($verifiedJournalData))
                    <div class="p-3.5 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="space-y-0.5">
                            <div class="font-semibold text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                                <flux:icon name="check-circle" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span>{{ $verifiedJournalData['message'] ?? 'Journal verified successfully.' }}</span>
                            </div>
                            @if(!empty($verifiedJournalData['title']))
                                <div class="text-[11px] text-emerald-700 dark:text-emerald-400/80 pl-5">
                                    Official Title: <strong>{{ $verifiedJournalData['title'] }}</strong>
                                    @if(!empty($verifiedJournalData['publisher']))
                                        &bull; Publisher: <strong>{{ $verifiedJournalData['publisher'] }}</strong>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <button 
                            type="button" 
                            wire:click="openAutoFillModal"
                            wire:loading.attr="disabled"
                            wire:target="openAutoFillModal"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white shadow-xs transition shrink-0 cursor-pointer disabled:opacity-50"
                        >
                            <flux:icon name="arrow-path" class="size-3.5" wire:loading.remove wire:target="openAutoFillModal" />
                            <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none" wire:loading wire:target="openAutoFillModal">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Auto-fill Details
                        </button>
                    </div>

                    @if(!empty($autoFillSuccessMessage))
                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200 flex items-center justify-between gap-2 transition animate-fade-in">
                            <div class="flex items-center gap-2">
                                <flux:icon name="check-badge" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span class="font-medium">{{ $autoFillSuccessMessage }}</span>
                            </div>
                            <button type="button" wire:click="$set('autoFillSuccessMessage', null)" class="text-emerald-600 hover:text-emerald-800 dark:hover:text-emerald-100 text-sm leading-none">&times;</button>
                        </div>
                    @endif
                @elseif($isJournalVerifiedOnline === false)
                    <div class="p-3.5 rounded-xl bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/60 text-xs text-rose-800 dark:text-rose-300 flex items-start gap-2">
                        <flux:icon name="exclamation-triangle" class="size-4 text-rose-500 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-semibold">Verification Alert:</span>
                            <span>{{ $verificationMessage ?? 'The entered ISSN could not be verified in official registries.' }}</span>
                        </div>
                    </div>
                @endif
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            e-ISSN <span class="text-zinc-400 font-normal lowercase">(or p-ISSN required)</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="e_issn" 
                            x-on:input="
                                formatIssnValue($event, 'e_issn');
                                if ($event.target.value.length === 9) {
                                    checkIssnInput($event.target.value, 'e_issn');
                                }
                            "
                            maxlength="9"
                            placeholder="e.g. 2321-9939"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm font-mono uppercase text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('e_issn') border-rose-400 @enderror"
                        />
                        @error('e_issn') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            p-ISSN <span class="text-zinc-400 font-normal lowercase">(or e-ISSN required)</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="p_issn" 
                            x-on:input="
                                formatIssnValue($event, 'p_issn');
                                if ($event.target.value.length === 9) {
                                    checkIssnInput($event.target.value, 'p_issn');
                                }
                            "
                            maxlength="9"
                            placeholder="e.g. 2321-9939"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm font-mono uppercase text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('p_issn') border-rose-400 @enderror"
                        />
                        @error('p_issn') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            ISSN Assignee Country
                        </label>
                        <x-searchable-select 
                            wire:model="issn_assignee_country" 
                            :options="$countries" 
                            placeholder="Select Country" 
                            search-placeholder="Search country..."
                            no-results-text="No result Found"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Journal Impact Factor
                        </label>
                        <input 
                            type="text" 
                            wire:model="journal_impact_factor" 
                            placeholder="e.g. 7.37"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                        />
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Impact Factor Assignee
                        </label>
                        <input 
                            type="text" 
                            wire:model="impact_factor_assignee_agency" 
                            placeholder="e.g. Google Scholar, Clarivate"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                        />
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Issue Frequency
                        </label>
                        <select 
                            wire:model="issue_frequency" 
                            class="w-full px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition cursor-pointer"
                        >
                            <option value="Monthly">Monthly</option>
                            <option value="Semi-monthly">Semi-monthly</option>
                            <option value="Bi-Monthly">Bi-Monthly</option>
                            <option value="weekly">weekly</option>
                            <option value="Bi-Weekly">Bi-Weekly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Half-Yearly">Half Yearly</option>
                            <option value="Yearly">Yearly / Annually</option>
                            <option value="Bi-Annual">Bi-Annual</option>
                            <option value="Semiannual">Semiannual</option>
                            <option value="Triennially">Triennially</option>
                            <option value="Continuously">Continuously</option>
                            <option value="36-issues-year">36 Issues / Year</option>
                            <option value="10-issues-year">10 Issues / Year</option>
                            <option value="9-issues-year">9 Issues / Year</option>
                            <option value="8-issues-year">8 Issues / Year</option>
                            <option value="5-issues-year">5 Issues / Year</option>
                            <option value="3-issues-year">3 Issues / Year</option>
                            <option value="7-issues-year">7 Issues / Year</option>
                            <option value="Irregular">Irregular</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. DISCIPLINES, LANGUAGES & SCOPE -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3 rounded-t-2xl">
                <div class="size-10 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                    <flux:icon name="academic-cap" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">3. Disciplines, Languages & Scope</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Accepted languages, subjects, and journal description.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- Languages & Disciplines side-by-side -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Languages (Left) -->
                    <div data-field-wrapper>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Languages in which manuscripts are accepted
                            </label>
                            <span class="text-[11px] text-zinc-400">Select or type custom language</span>
                        </div>
                        <x-tag-select 
                            wire:model="languages" 
                            :options="$defaultLanguages" 
                            placeholder="Select language (Hindi, English)..."
                            :allow-custom="true"
                            :has-error="$errors->has('languages')"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Select from allowed languages (Hindi, English) or type custom and press Enter.</p>
                        @error('languages') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Journal Discipline (Subjects) (Right side) -->
                    <div data-field-wrapper>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Journal Discipline (Subjects)
                            </label>
                            <span class="text-[11px] text-zinc-400">Select or type custom subject</span>
                        </div>
                        <x-tag-select 
                            wire:model="journal_subjects" 
                            :options="$defaultSubjects" 
                            placeholder="Select discipline (Socila, Developing)..."
                            :allow-custom="true"
                            :has-error="$errors->has('journal_subjects')"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">Select from allowed disciplines (Socila, Developing) or type custom and press Enter.</p>
                        @error('journal_subjects') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Description -->
                <div data-field-wrapper>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        About Journal <span class="text-zinc-400 font-normal">(Brief Description in 200 words)</span> <span class="text-rose-500">*</span>
                    </label>
                    <textarea 
                        wire:model="journal_description" 
                        rows="5"
                        placeholder="Provide an overview of aims, scopes, peer-review standards, and focus areas..."
                        class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('journal_description') border-rose-400 @enderror"
                    ></textarea>
                    @error('journal_description') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- 4. EDITORIAL BOARD & CHIEF CONTACT -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3 rounded-t-2xl">
                <div class="size-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <flux:icon name="user-group" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">4. Editorial Team & Contact Info</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Editor-in-Chief details and editorial board members with roles.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- Editor in Chief -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Editor in Chief or Contact Person Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="contact_name" 
                            placeholder="e.g. Dr. John Doe"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('contact_name') border-rose-400 @enderror"
                        />
                        @error('contact_name') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Editor in Chief Email id (Journal) <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            wire:model="contact_email" 
                            placeholder="editor@journal.org"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('contact_email') border-rose-400 @enderror"
                        />
                        @error('contact_email') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Mobile No (Chief Editor / Manager) <span class="text-rose-500">*</span>
                        </label>
                        <x-phone-input 
                            wire:model="mobile" 
                            country-code-model="country_code"
                            placeholder="1234567890" 
                            :has-error="$errors->has('mobile')"
                        />
                        @error('mobile') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Add Editorial Board Members -->
                <div class="pt-4 border-t border-zinc-200/80 dark:border-zinc-800 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Add Editorial Board Members</h3>
                        <p class="text-xs text-zinc-400">Search member by name and choose their role to add to the editorial board.</p>
                    </div>

                    <!-- Search Input and Role Select side-by-side -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                        <!-- Member Search (sm:col-span-8) -->
                        <div class="sm:col-span-8 relative" x-data="{ open: @entangle('showMemberDropdown') }" @click.outside="open = false">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Add Editorial Board Members
                            </label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="memberSearchQuery" 
                                    x-on:focus="if ($wire.memberSearchQuery && $wire.memberSearchQuery.length >= 2) open = true"
                                    x-on:keydown.enter.prevent="$wire.addEditorialMember()"
                                    placeholder="Search member name..." 
                                    class="w-full px-4 py-2.5 pl-10 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                                />
                                <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                                    <flux:icon name="magnifying-glass" class="size-4" />
                                </div>
                                <div wire:loading wire:target="memberSearchQuery" class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none">
                                    <svg class="animate-spin h-4 w-4 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Dropdown Results -->
                            @if($showMemberDropdown)
                                <div 
                                    x-show="open" 
                                    class="absolute left-0 right-0 z-30 mt-1.5 max-h-64 overflow-y-auto bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl divide-y divide-zinc-100 dark:divide-zinc-700/60"
                                >
                                    @if(!empty($memberSearchResults))
                                        <div class="px-3.5 py-1.5 bg-zinc-50 dark:bg-zinc-800/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500">
                                            Select Member to Add (as {{ $selectedRole }})
                                        </div>
                                        @foreach($memberSearchResults as $res)
                                            <button 
                                                type="button" 
                                                wire:click="selectEditorialMember('{{ $res['id'] }}', '{{ addslashes($res['name']) }}', '{{ addslashes($res['photo']) }}')" 
                                                @click="open = false"
                                                class="w-full text-left px-3.5 py-2.5 hover:bg-sky-50 dark:hover:bg-zinc-700/60 transition flex items-center gap-3 cursor-pointer group"
                                            >
                                                <img 
                                                    src="{{ (!empty($res['photo']) && !str_starts_with($res['photo'], 'http')) ? asset($res['photo']) : ($res['photo'] ?: asset('assets/images/vector.png')) }}" 
                                                    alt="{{ $res['name'] }}" 
                                                    class="w-8 h-8 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 bg-zinc-100"
                                                    onerror="this.onerror=null; this.src='{{ asset('assets/images/vector.png') }}';"
                                                />
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white group-hover:text-brand truncate">
                                                        {{ $res['name'] }}
                                                    </div>
                                                    @if(!empty($res['affiliation']))
                                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                                            {{ $res['affiliation'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <flux:icon name="plus" class="size-4 text-brand opacity-0 group-hover:opacity-100 transition" />
                                            </button>
                                        @endforeach
                                    @else
                                        <div class="p-3 text-center text-xs text-zinc-500">
                                            No registered members found for "{{ $memberSearchQuery }}"
                                            <div class="mt-2">
                                                <button 
                                                    type="button" 
                                                    wire:click="addEditorialMember" 
                                                    @click="open = false"
                                                    class="inline-flex items-center gap-1 px-3 py-1 bg-brand/10 hover:bg-brand/20 text-brand text-xs font-semibold rounded-lg transition cursor-pointer"
                                                >
                                                    <flux:icon name="plus" class="size-3.5" />
                                                    <span>Add "{{ $memberSearchQuery }}"</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Role Select (sm:col-span-4) - next to Add Editorial Board Members -->
                        <div class="sm:col-span-4">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Add Editorial Board Members
                            </label>
                            <select class="select2 form-control journal_role_name w-full px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition cursor-pointer" name="name" wire:model.live="selectedRole">
                                @foreach ($data['journal_role'] as $journal_role)
                                    <option value="{{$journal_role['id']}}"  >{{$journal_role['name']}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Selected Member Cards -->
                    @if(!empty($editorial_members))
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                            @foreach($editorial_members as $index => $member)
                                @php
                                    $mPhoto = $member['photo'] ?? 'assets/images/vector.png';
                                    $mPhotoSrc = (!empty($mPhoto) && !str_starts_with($mPhoto, 'http')) ? asset($mPhoto) : ($mPhoto ?: asset('assets/images/vector.png'));
                                @endphp
                                <div class="border border-sky-400 bg-white dark:bg-zinc-900 rounded-xl p-3 flex items-center justify-between gap-3 shadow-xs transition hover:shadow-sm">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img 
                                            src="{{ $mPhotoSrc }}" 
                                            alt="{{ $member['name'] ?? 'Member' }}" 
                                            class="w-10 h-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 bg-zinc-100"
                                            onerror="this.onerror=null; this.src='{{ asset('assets/images/vector.png') }}';"
                                        />
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm text-zinc-900 dark:text-white truncate">
                                                {{ $member['name'] ?? 'Member' }}
                                            </div>
                                            <select 
                                                wire:model="editorial_members.{{ $index }}.role_id" 
                                                class="select2 form-control journal_role_name text-xs text-zinc-600 dark:text-zinc-400 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none cursor-pointer mt-0.5 truncate block max-w-full font-medium"
                                                name="name"
                                            >
                                                @foreach ($data['journal_role'] as $journal_role)
                                                    <option value="{{$journal_role['id']}}" @selected(($member['role_id'] ?? '') == $journal_role['id'] || ($member['role_id'] ?? '') == $journal_role['name'])>{{$journal_role['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <button 
                                        type="button" 
                                        wire:click="removeEditorialMember({{ $index }})" 
                                        class="text-red-500 hover:text-red-700 text-lg font-bold leading-none p-1 transition cursor-pointer shrink-0" 
                                        title="Remove Member"
                                    >
                                        ✕
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- 5. LOCATION / ADDRESS -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3 rounded-t-2xl">
                <div class="size-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <flux:icon name="map-pin" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">5. Journal Address & Geo-Location</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Headquarters location and regional postal specifications.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <div data-field-wrapper>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Address <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="address" 
                        placeholder="Street address, building, suite..."
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('address') border-rose-400 @enderror"
                    />
                    @error('address') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div data-field-wrapper>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Country <span class="text-rose-500">*</span>
                            </label>
                            <span wire:loading wire:target="country" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                        </div>
                        <x-searchable-select 
                            wire:model.live="country" 
                            :options="$countries" 
                            placeholder="Select Country" 
                            search-placeholder="Search country..."
                            no-results-text="No result Found"
                            :has-error="$errors->has('country')"
                        />
                        @error('country') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                State @if(!empty($country) && count($states) > 0) <span class="text-rose-500">*</span> @endif
                            </label>
                            <span wire:loading wire:target="state" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                        </div>
                        <x-searchable-select 
                            wire:model.live="state" 
                            :options="$states" 
                            :disabled="empty($country)"
                            disabled-placeholder="Select Country First"
                            placeholder="Select State" 
                            search-placeholder="Search state..."
                            no-results-text="No result Found"
                            wire:key="create-state-select-{{ $country ?: 'none' }}"
                            :has-error="$errors->has('state')"
                        />
                        @error('state') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                City @if(!empty($state) && count($cities) > 0) <span class="text-rose-500">*</span> @endif
                            </label>
                            <span wire:loading wire:target="city" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                        </div>
                        <x-searchable-select 
                            wire:model="city" 
                            :options="$cities" 
                            :disabled="empty($state)"
                            disabled-placeholder="Select State First"
                            placeholder="Select City" 
                            search-placeholder="Search city..."
                            no-results-text="No result Found"
                            wire:key="create-city-select-{{ $state ?: 'none' }}"
                            :has-error="$errors->has('city')"
                        />
                        @error('city') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div data-field-wrapper>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Postal/Zip Code: <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model="zipcode" 
                            placeholder="e.g. 382470"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('zipcode') border-rose-400 @enderror"
                        />
                        @error('zipcode') <p class="error-message text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. COMPLIANCE & LICENSING QUESTIONS (YES / NO) -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3 rounded-t-2xl">
                <div class="size-10 rounded-xl bg-teal-500/10 border border-teal-500/20 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                    <flux:icon name="shield-check" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">6. Open-Access, Peer Review & Compliance Policies</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Editorial standards, copyright retention, archiving, and indexing compliance.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 divide-y divide-zinc-100 dark:divide-zinc-800 space-y-5">
                <!-- Q1: Open Access -->
                <div class="pt-4 first:pt-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="max-w-xl">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">Does your Journal use an open-access license (i.e. Creative Commons)?</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="JournalOpenAccess" value="1" class="text-brand focus:ring-brand" />
                            <span>Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="JournalOpenAccess" value="0" class="text-brand focus:ring-brand" />
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <!-- Q2: COPE Guidelines -->
                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="max-w-xl">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">Does your journal follow COPE Guidelines?</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="COPEguidelines" value="1" class="text-brand focus:ring-brand" />
                            <span>Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="COPEguidelines" value="0" class="text-brand focus:ring-brand" />
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <!-- Q3: Copyright -->
                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="max-w-xl">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">Do authors retain copyright and full publishing rights without any restrictions?</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="restrictions" value="1" class="text-brand focus:ring-brand" />
                            <span>Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="restrictions" value="0" class="text-brand focus:ring-brand" />
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <!-- Q4: Peer Review -->
                <div 
                    x-data="{ 
                        followPeer: @entangle('followpeer'),
                        showOther: @entangle('other')
                    }" 
                    data-field-wrapper 
                    class="pt-4 space-y-4"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="max-w-xl">
                            <p class="text-xs font-bold text-zinc-900 dark:text-white">Does your journal follow peer review?</p>
                        </div>
                        <div class="flex items-center gap-6 text-xs font-semibold">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="radio" 
                                    x-model="followPeer" 
                                    value="1" 
                                    class="text-brand focus:ring-brand" 
                                />
                                <span>Yes</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="radio" 
                                    x-model="followPeer" 
                                    value="0" 
                                    class="text-brand focus:ring-brand" 
                                />
                                <span>No</span>
                            </label>
                        </div>
                    </div>

                    <div 
                        x-show="followPeer == '1'" 
                        x-cloak 
                        class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-700/80 space-y-3"
                    >
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            Select Peer Review Type(s)
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" wire:model="EditorialReview" class="rounded text-brand focus:ring-brand" />
                                <span>Editorial review</span>
                            </label>
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" wire:model="PeerReview" class="rounded text-brand focus:ring-brand" />
                                <span>Peer review</span>
                            </label>
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" wire:model="AnonymousPeerReview" class="rounded text-brand focus:ring-brand" />
                                <span>Anonymous peer review</span>
                            </label>
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" wire:model="DoubleAnonymousPeerReview" class="rounded text-brand focus:ring-brand" />
                                <span>Double anonymous peer review</span>
                            </label>
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" wire:model="PostPublicationPeerReview" class="rounded text-brand focus:ring-brand" />
                                <span>Post-publication peer review</span>
                            </label>
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" wire:model="OpenPeerReview" class="rounded text-brand focus:ring-brand" />
                                <span>Open peer review</span>
                            </label>
                            <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-zinc-800 dark:text-zinc-200 cursor-pointer hover:border-brand/40 transition">
                                <input type="checkbox" x-model="showOther" class="rounded text-brand focus:ring-brand" />
                                <span>Other</span>
                            </label>

                            <div 
                                x-show="showOther" 
                                x-cloak
                                class="sm:col-span-1 md:col-span-2"
                            >
                                <input 
                                    type="text" 
                                    wire:model="otherPeer" 
                                    placeholder="Please specify other peer review process..."
                                    class="w-full h-full min-h-[38px] px-3.5 py-2 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Q5: Plagiarism -->
                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="max-w-xl">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">Does the journal regularly conduct plagiarism checks on submitted articles?</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="plagiarism" value="1" class="text-brand focus:ring-brand" />
                            <span>Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="plagiarism" value="0" class="text-brand focus:ring-brand" />
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <!-- Q6: Publication Platform -->
                <div class="pt-4 grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div>
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">What is the publication platform utilized by your journal?</p>
                    </div>
                    <div>
                        <select 
                            wire:model="publicationPlatform" 
                            class="w-full px-4 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand transition"
                        >
                            <option value="Open Journal Systems (OJS). The Public Knowledge Project">Open Journal Systems (OJS). The Public Knowledge Project.</option>
                            <option value="Own Customized Platform">Own Customized Platform.</option>
                            <option value="Editorial Manager">Editorial Manager.</option>
                            <option value="Digital Commons from Elsevier">Digital Commons from Elsevier.</option>
                            <option value="Ambra. Topaz">Ambra. Topaz.</option>
                            <option value="Annotum. JATS-based workflow">Annotum. JATS-based workflow.</option>
                            <option value="CLEO Various tools related to OpenEdition Journal">CLEO Various tools related to OpenEdition Journals.</option>
                            <option value="DiVA. Electronic Publishing Centre">DiVA. Electronic Publishing Centre.</option>
                            <option value="DPubS. Cornell University Library and Pennsylvania State University">DPubS. Cornell University Library and Pennsylvania State University.</option>
                            <option value="E-Journal">E-Journal.</option>
                            <option value="ePublishing Toolkit. Max Planck Gesellschaft">ePublishing Toolkit. Max Planck Gesellschaft.</option>
                            <option value="GAPworks. German Academic Publishers">GAPworks. German Academic Publishers.</option>
                            <option value="HyperJournal. The University of Pisa">HyperJournal. The University of Pisa.</option>
                            <option value="Janeway.The University of London">Janeway.The University of London.</option>
                            <option value="Lodel. OpenEdition Journal">Lodel. OpenEdition Journal.</option>
                            <option value="OpenACS">OpenACS.</option>
                            <option value="PeerLibrary. UC Berkeley">PeerLibrary. UC Berkeley.</option>
                            <option value="SOPS. From SciX">SOPS. From SciX.</option>
                            <option value="KU ScholarWorks">KU ScholarWorks</option>
                        </select>
                    </div>
                </div>

                <!-- Q7: APC Fees -->
                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="max-w-xl">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">Are there any fees associated with publishing an article in the journal, (APCs)?</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="feesAssociatedWithPublishing" value="1" class="text-brand focus:ring-brand" />
                            <span>Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="feesAssociatedWithPublishing" value="0" class="text-brand focus:ring-brand" />
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <!-- Q8: Preservation -->
                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="max-w-xl">
                        <p class="text-xs font-bold text-zinc-900 dark:text-white">Does the journal have a preservation service and archiving policy in place?</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preservation" value="1" class="text-brand focus:ring-brand" />
                            <span>Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preservation" value="0" class="text-brand focus:ring-brand" />
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <!-- Q9: DOI -->
                <div 
                    x-data="{ hasDoi: @entangle('DOI') }" 
                    class="pt-4 space-y-3"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="max-w-xl">
                            <p class="text-xs font-bold text-zinc-900 dark:text-white">Does your journal have DOI?</p>
                        </div>
                        <div class="flex items-center gap-6 text-xs font-semibold">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="radio" 
                                    x-model="hasDoi" 
                                    value="1" 
                                    class="text-brand focus:ring-brand" 
                                />
                                <span>Yes</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input 
                                    type="radio" 
                                    x-model="hasDoi" 
                                    value="0" 
                                    class="text-brand focus:ring-brand" 
                                />
                                <span>No</span>
                            </label>
                        </div>
                    </div>
                    <div 
                        x-show="hasDoi == '1'" 
                        x-cloak 
                        class="pt-2 border-t border-zinc-100 dark:border-zinc-800"
                    >
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Enter Journal DOI / DOI Prefix
                        </label>
                        <input 
                            type="text" 
                            wire:model="doi" 
                            placeholder="e.g. 10.xxxx or https://doi.org/10.xxxx"
                            class="w-full px-4 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand transition"
                        />
                        @error('doi') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. INDEXING AGENCIES -->
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center justify-between gap-3 rounded-t-2xl">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <flux:icon name="check-badge" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white">7. Indexing Agency & URLs</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Select active indexers and provide the public profile URL for each indexing service.</p>
                    </div>
                </div>
            </div>

            <div 
                x-data="{ 
                    agencySearch: '',
                    matches(name) {
                        if (!this.agencySearch || !this.agencySearch.trim()) return true;
                        return name.toLowerCase().includes(this.agencySearch.toLowerCase().trim());
                    }
                }"
                class="p-6 sm:p-8 space-y-4"
            >
                <!-- Search / Filter bar for Indexing Agencies -->
                <div class="flex items-center justify-between gap-4 pb-1">
                    <div class="relative max-w-sm w-full">
                        <flux:icon name="magnifying-glass" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" />
                        <input 
                            type="text" 
                            x-model="agencySearch" 
                            placeholder="Search indexing agency..." 
                            class="w-full pl-9 pr-8 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                        />
                        <button 
                            type="button" 
                            x-show="agencySearch" 
                            @click="agencySearch = ''" 
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 text-sm"
                            title="Clear search"
                            style="display: none;"
                        >
                            &times;
                        </button>
                    </div>
                    <span class="text-xs text-zinc-400 hidden sm:inline">
                        Type to filter agencies
                    </span>
                </div>

                <!-- Indexing Agencies Table / List with Scrollbar -->
                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-white dark:bg-zinc-900/40">
                    <!-- Table Header -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 px-4 py-3 bg-zinc-50 dark:bg-zinc-900/80 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 select-none">
                        <div class="md:col-span-5 flex items-center gap-2">
                            <span>Indexing Agency</span>
                        </div>
                        <div class="md:col-span-7">
                            <span>URL of Indexing Agency</span>
                        </div>
                    </div>

                    <!-- Scrollable Rows Container -->
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80 max-h-[460px] overflow-y-auto">
                        @foreach($agencies as $agency)
                            @php
                                $logoImg = $agency['image'] ?? null;
                                $isChecked = !empty($indexing[$agency['key']]['checked']);
                            @endphp
                            <div 
                                x-show="matches('{{ addslashes($agency['name']) }}')"
                                class="grid grid-cols-1 md:grid-cols-12 gap-3 px-4 py-2.5 items-center transition hover:bg-zinc-50/70 dark:hover:bg-zinc-900/30 has-[:checked]:bg-brand/5 dark:has-[:checked]:bg-brand/10"
                            >
                                <!-- Agency Column: Checkbox, Logo, Name -->
                                <div class="md:col-span-5 flex items-center gap-3 min-w-0">
                                    <input 
                                        type="checkbox" 
                                        wire:model="indexing.{{ $agency['key'] }}.checked" 
                                        id="agency-chk-{{ $agency['key'] }}"
                                        class="rounded text-brand focus:ring-brand size-4 cursor-pointer shrink-0" 
                                    />
                                    
                                    <!-- Agency Logo / Thumbnail -->
                                    <div class="size-8 rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center p-1 shrink-0 overflow-hidden">
                                        @if($logoImg)
                                            <img 
                                                src="{{ $logoImg }}" 
                                                alt="{{ $agency['name'] }}" 
                                                class="max-h-full max-w-full object-contain"
                                                loading="lazy"
                                                onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';"
                                            />
                                            <div class="size-full items-center justify-center text-zinc-400" style="display: none;">
                                                <flux:icon name="bookmark" class="size-3.5" />
                                            </div>
                                        @else
                                            <div class="size-full flex items-center justify-center text-zinc-400">
                                                <flux:icon name="bookmark" class="size-3.5" />
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Agency Name -->
                                    <label for="agency-chk-{{ $agency['key'] }}" class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate cursor-pointer select-none">
                                        {{ $agency['name'] }}
                                    </label>
                                </div>

                                <!-- URL Column -->
                                <div class="md:col-span-7">
                                    <input 
                                        type="url" 
                                        wire:model="indexing.{{ $agency['key'] }}.url" 
                                        placeholder="URL of {{ $agency['name'] }}"
                                        class="w-full px-3 py-1.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 rounded-lg text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div 
                    x-show="agencySearch && !{{ \Illuminate\Support\Js::from(array_column($agencies, 'name')) }}.some(n => matches(n))" 
                    class="py-8 text-center text-xs text-zinc-400"
                    style="display: none;"
                >
                    No indexing agency found matching &quot;<span x-text="agencySearch" class="font-semibold text-zinc-700 dark:text-zinc-200"></span>&quot;
                </div>
            </div>
        </div>

        <!-- 8. STATUS & SUBMIT -->
        <div class="bg-white dark:bg-[#111c26] p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <label class="text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">Status:</label>
                <select 
                    wire:model="status" 
                    class="px-3 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-semibold text-zinc-900 dark:text-white focus:outline-none focus:border-brand"
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <a 
                    href="{{ route('admin.journals.index') }}" 
                    wire:navigate 
                    class="px-4 py-2.5 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition"
                >
                    Cancel
                </a>

                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] rounded-xl shadow-md shadow-brand/20 transition inline-flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 whitespace-nowrap min-w-[190px]"
                >
                    <flux:icon wire:loading wire:target="save" name="arrow-path" class="size-3.5 animate-spin shrink-0" />
                    <span wire:loading.remove wire:target="save">Save & Register Journal</span>
                    <span wire:loading wire:target="save">Saving Journal...</span>
                </button>
            </div>
        </div>
    </form>

    <!-- Admin Journal Modals Component -->
    <x-admin.journal.modals 
        :show-auto-fill-modal="$showAutoFillModal" 
        :auto-fill-diff="$autoFillDiff" 
        :existing-db-journal-info="$existingDbJournalInfo" 
        :verified-source-label="$verifiedSourceLabel" 
    />

    <script>
        document.addEventListener('livewire:initialized', () => {
            if (window.Livewire) {
                Livewire.hook('commit', ({ succeed }) => {
                    succeed(({ effect }) => {
                        if (effect && effect.errors && Object.keys(effect.errors).length > 0) {
                            setTimeout(() => {
                                window.dispatchEvent(new CustomEvent('scroll-to-first-error'));
                            }, 50);
                        }
                    });
                });
            }
        });
    </script>
</div>