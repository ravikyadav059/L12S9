<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Research;
use App\Models\ReviewerProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

/**
 * Admin User Edit Component (Livewire Volt Single-File Component)
 *
 * Master coordinator component for administrative user management.
 * Provides full profile control with isolated, high-performance child Volt components.
 *
 * Modular Child Volt Components (Independent & Isolated):
 * - partials/header.blade.php           : Breadcrumb, title, Unique ID, and public profile link
 * - partials/status-bar.blade.php       : Independent child Volt component (profile status, verification, roles, badges, conference)
 * - partials/profile-info.blade.php      : Independent child Volt component (avatar photo, personal details, cascading address lookups)
 * - partials/about.blade.php            : Independent child Volt component (about / bio, skills tags, research interests, social links)
 * - partials/experience.blade.php       : Independent child Volt component (work experience CRUD & error boundary)
 * - partials/education.blade.php        : Independent child Volt component (education history CRUD & error boundary)
 * - partials/publications.blade.php     : Independent child Volt component (publications CRUD & error boundary)
 * - partials/projects.blade.php         : Independent child Volt component (projects CRUD & error boundary)
 * - partials/seminars.blade.php         : Independent child Volt component (conferences & seminars CRUD & error boundary)
 * - partials/certificates.blade.php     : Independent child Volt component (certificates CRUD & error boundary)
 * - partials/awards.blade.php           : Independent child Volt component (awards & achievements CRUD & error boundary)
 * - partials/thesis.blade.php           : Independent child Volt component (thesis guided CRUD & error boundary)
 * - partials/memberships.blade.php      : Independent child Volt component (professional memberships CRUD & error boundary)
 * - partials/patents.blade.php          : Independent child Volt component (patents CRUD & error boundary)
 * - partials/invited-positions.blade.php: Independent child Volt component (invited positions CRUD & error boundary)
 * - partials/journal-roles.blade.php     : Independent child Volt component (journal editorial roles CRUD & error boundary)
 */
new #[Layout('components.layouts.admin')] #[Title('Edit User - Admin')] class extends Component {
    /**
     * The User Eloquent model instance being edited.
     */
    public User $user;

    /**
     * Unique profile identifier (e.g. S9-032026-1006999).
     */
    public string $profileId = '';

    // =========================================================================
    // SECTION: Personal & Display Name Properties
    // =========================================================================

    #[Validate('nullable|string|max:50')]
    public string $prefix = '';

    #[Validate('required|string|max:100', message: 'First name is required.')]
    public string $first_name = '';

    #[Validate('required|string|max:100', message: 'Last name is required.')]
    public string $last_name = '';

    /**
     * Full combined name (computed from prefix, first name, and last name).
     */
    public string $fullname = '';

    #[Validate('nullable|string|max:150')]
    public string $profile_title = '';

    // =========================================================================
    // SECTION: Contact Information
    // =========================================================================

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:50')]
    public string $mobile = '';

    public string $countrycode = '+91';

    public string $countryiso2 = '';

    /**
     * Relative path or URL to the user's profile avatar photo.
     */
    public ?string $photo = null;

    // =========================================================================
    // SECTION: Address & Geo-Location Properties
    // =========================================================================

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string')]
    public string $country = '';

    #[Validate('nullable|string')]
    public string $state = '';

    #[Validate('nullable|string')]
    public string $city = '';

    #[Validate('nullable|string|max:30')]
    public string $zipcode = '';

    // =========================================================================
    // SECTION: Security & Password Modal State
    // =========================================================================

    public bool $showPasswordModal = false;
    public string $new_password = '';
    public string $new_password_confirmation = '';

    /**
     * Error boundary flag and message for top-level component safety.
     */
    public bool $hasError = false;
    public ?string $errorMessage = null;

    /**
     * Top-level error boundary to catch and stop propagation of any unexpected runtime errors.
     *
     * @param  \Throwable  $e
     * @param  callable  $stopPropagation
     * @return void
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        report($e);
        $stopPropagation();
        $this->hasError = true;
        $this->errorMessage = 'An unexpected error occurred: ' . $e->getMessage();
        session()->flash('photo_error', 'An unexpected error occurred: ' . $e->getMessage());
    }

    /**
     * Mount and initialize the component with data from User and ReviewerProfile models.
     *
     * @param User $user The user being edited
     * @return void
     */
    public function mount(User $user): void
    {
        // Enforce that user has a valid unique_id for admin routing
        if (empty($user->unique_id)) {
            abort(404, 'User does not have a unique_id.');
        }

        $this->user = $user;
        $this->profileId = (string) $this->user->unique_id;
        $this->prefix = (string) ($this->user->prefix ?? '');
        $this->first_name = (string) ($this->user->first_name ?? '');
        $this->last_name = (string) ($this->user->last_name ?? '');

        // Fallback: Split full name into first and last name if first name is missing
        if (empty($this->first_name) && ! empty($this->user->fullname ?: $this->user->name)) {
            $rawName = trim((string) ($this->user->fullname ?: $this->user->name));
            $parts = preg_split('/\s+/', $rawName, 2);
            $this->first_name = $parts[0] ?? '';
            if (empty($this->last_name) && isset($parts[1])) {
                $this->last_name = $parts[1];
            }
        }
        $this->fullname = trim($this->first_name . ' ' . $this->last_name);
        $this->email = (string) ($this->user->email ?? '');
        $this->mobile = (string) ($this->user->mobile ?? '');
        $this->countrycode = (string) ($this->user->countrycode ?? '+91');
        $this->countryiso2 = (string) ($this->user->countryiso2 ?? '');
        $this->photo = ! empty($this->user->photo) ? (string) $this->user->photo : null;

        try {
            // Hydrate secondary attributes from ReviewerProfile
            $reviewerProfile = $this->user->reviewerProfile;

            $this->profile_title = (string) ($reviewerProfile?->designation ?? '');
            $this->address = (string) ($reviewerProfile?->address ?? '');
            $this->country = (string) ($reviewerProfile?->country ?? '');
            $this->state = (string) ($reviewerProfile?->state ?? '');
            $this->city = (string) ($reviewerProfile?->city ?? '');
            $this->zipcode = (string) ($reviewerProfile?->zipcode ?? '');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // =========================================================================
    // SECTION: Livewire Real-time Model Synchronization Hooks
    // =========================================================================

    /**
     * Recalculate full name when first name is updated.
     */
    public function updatedFirstName(): void
    {
        $this->fullname = trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Recalculate full name when last name is updated.
     */
    public function updatedLastName(): void
    {
        $this->fullname = trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Reset dependent state and city selections when country changes.
     */
    public function updatedCountry(): void
    {
        $this->state = '';
        $this->city = '';
        $this->resetErrorBag(['country', 'state', 'city']);
    }

    /**
     * Reset dependent city selection when state changes.
     */
    public function updatedState(): void
    {
        $this->city = '';
        $this->resetErrorBag(['state', 'city']);
    }

    /**
     * Clear error bag for city on modification.
     */
    public function updatedCity(): void
    {
        $this->resetErrorBag(['city']);
    }

    // =========================================================================
    // SECTION: Cascading Geo-Location Lookups (Cached)
    // =========================================================================

    /**
     * Get cached list of states for currently selected country.
     *
     * @return array<int|string, string>
     */
    public function getStatesList(): array
    {
        return ! empty($this->country) ? State::getCachedByCountry($this->country) : [];
    }

    /**
     * Get cached list of cities for currently selected state.
     *
     * @return array<int|string, string>
     */
    public function getCitiesList(): array
    {
        return ! empty($this->state) ? City::getCachedByState($this->state) : [];
    }

    /**
     * Provide cached dropdown datasets to the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'countries' => Country::getCachedAll(),
            'states' => $this->getStatesList(),
            'cities' => $this->getCitiesList(),
        ];
    }

    // =========================================================================
    // SECTION: Profile Avatar & Image Cropper Handlers
    // =========================================================================

    /**
     * Save cropped avatar image data (base64 string received from Cropper.js).
     *
     * @param string $base64Data Data URL containing base64 PNG image
     * @return void
     */
    public function saveCroppedPhoto(string $base64Data): void
    {
        if (empty($base64Data)) {
            return;
        }

        $rawBase64 = $base64Data;
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $rawBase64 = substr($base64Data, strpos($base64Data, ',') + 1);
        }

        $decoded = base64_decode($rawBase64);
        if ($decoded === false) {
            session()->flash('photo_error', 'Failed to process image data.');
            return;
        }

        $uploadsDir = public_path('uploads');
        if (! File::isDirectory($uploadsDir)) {
            File::makeDirectory($uploadsDir, 0755, true, true);
        }

        // Delete previous local photo file if present to avoid storage accumulation
        if (! empty($this->user->photo) && str_starts_with($this->user->photo, 'uploads/') && File::exists(public_path($this->user->photo))) {
            File::delete(public_path($this->user->photo));
        }

        // Save file with format: uploads/profile_{timestampMs}_{time}_{random6}.png
        $filename = 'profile_' . now()->getTimestampMs() . '_' . time() . '_' . Str::random(6) . '.png';
        $fullPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($fullPath, $decoded);

        $relativePath = 'uploads/' . $filename;
        $this->user->photo = $relativePath;
        $this->user->save();

        $this->photo = $relativePath;
        session()->flash('message', 'Profile photo updated successfully.');
    }

    /**
     * Remove the user's avatar image from filesystem and database.
     */
    public function removePhoto(): void
    {
        if (! empty($this->user->photo) && str_starts_with($this->user->photo, 'uploads/') && File::exists(public_path($this->user->photo))) {
            File::delete(public_path($this->user->photo));
        }

        $this->user->photo = null;
        $this->user->save();

        $this->photo = null;
        session()->flash('message', 'Profile photo removed successfully.');
    }

    // =========================================================================
    // SECTION: Status Bar Compatibility Proxies
    // =========================================================================

    public function updateProfileStatus(string $val): void
    {
        $this->user->status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Profile status updated.');
    }

    public function updateVerifiedUser(string $val): void
    {
        $this->user->verified = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Verified user status updated.');
    }

    public function updatePublisherStatus(string $val): void
    {
        $this->user->publisher_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Publisher status updated.');
    }

    public function updateReviewerStatus(string $val): void
    {
        $this->user->reviewer_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Reviewer status updated.');
    }

    public function updateBadgeType(string $val): void
    {
        $this->user->badge_type = $val;
        $this->user->save();
        session()->flash('quick_message', 'Badge type updated.');
    }

    public function updateUserRole(string $val): void
    {
        $this->user->role = $val;
        $this->user->save();
        session()->flash('quick_message', 'User role updated.');
    }

    public function toggleConference(): void
    {
        $this->user->enable_conference = ! ($this->user->enable_conference ?? false);
        $this->user->save();
        session()->flash('quick_message', 'Conference settings updated.');
    }

    // =========================================================================
    // SECTION: Security & Password Management Modal
    // =========================================================================

    /**
     * Open password change modal and reset input states.
     */
    public function openPasswordModal(): void
    {
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->resetErrorBag();
        $this->showPasswordModal = true;
    }

    /**
     * Close password change modal and clean sensitive inputs.
     */
    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->resetErrorBag();
    }

    /**
     * Validate and update user password with bcrypt hash.
     */
    public function updatePassword(): void
    {
        $this->validate([
            'new_password' => 'required|min:6|confirmed',
        ]);

        $this->user->password = Hash::make($this->new_password);
        $this->user->save();

        $this->closePasswordModal();
        $this->dispatch('password-updated');
        session()->flash('message', 'Password updated successfully for ' . $this->profileId);
    }

    // =========================================================================
    // SECTION: Primary User Profile & Address Persistence
    // =========================================================================

    /**
     * Validate and save primary user information and address details.
     * Persists base identity to the User model and designation/address to ReviewerProfile.
     */
    public function save(): void
    {
        $this->validate();

        $this->first_name = trim($this->first_name);
        $this->last_name = trim($this->last_name);
        $this->fullname = trim($this->first_name . ' ' . $this->last_name);

        $this->user->prefix = trim($this->prefix);
        $this->user->first_name = $this->first_name;
        $this->user->last_name = $this->last_name;
        $this->user->fullname = $this->fullname;
        $this->user->name = $this->fullname;
        $this->user->email = strtolower(trim($this->email));
        $this->user->mobile = trim($this->mobile);
        $this->countrycode = trim($this->countrycode);
        $this->user->countrycode = $this->countrycode;
        if (! empty($this->countryiso2)) {
            $this->user->countryiso2 = trim($this->countryiso2);
        }

        $this->user->save();

        // Save designation (Profile Title) and address fields using the model relationship
        $reviewerProfile = $this->user->reviewerProfile ?? $this->user->reviewerProfile()->make([
            'unique_id' => (string) ($this->user->unique_id ?? $this->profileId),
            'status' => (string) $this->user->getStatusCode(),
        ]);

        $reviewerProfile->designation = trim($this->profile_title);
        $reviewerProfile->address = trim($this->address);
        $reviewerProfile->country = trim($this->country);
        $reviewerProfile->state = trim($this->state);
        $reviewerProfile->city = trim($this->city);
        $reviewerProfile->zipcode = trim($this->zipcode);
        $reviewerProfile->save();

        session()->flash('message', 'User ' . ($this->profileId) . ' changes saved successfully.');
        $this->dispatch('scroll-to-top');
    }
}; ?>

<div 
    class="space-y-6" 
    x-data="{
        showPasswordModal: false,
        showEmailModal: false,
        canEditEmail: false,
        showCropModal: false,
        isProcessingCrop: false,
        cropper: null,
        imageSrc: '',
        mainInitialSnapshot: '',
        isMainDirty: false,

        init() {
            this.$nextTick(() => {
                this.markMainClean();
            });
        },

        getMainSnapshot() {
            const form = document.getElementById('main-user-edit-form');
            if (!form) return '';
            return Array.from(form.querySelectorAll('[wire\\:model]'))
                .map(el => (el.getAttribute('wire:model') || '') + '=' + (el.value || '').trim())
                .join('&');
        },

        markMainClean() {
            this.mainInitialSnapshot = this.getMainSnapshot();
            this.isMainDirty = false;
        },

        checkMainDirty() {
            this.isMainDirty = (this.getMainSnapshot() !== this.mainInitialSnapshot);
        },

        unlockEmail() {
            if (this.canEditEmail) return;
            this.showEmailModal = true;
        },

        confirmUnlockEmail() {
            this.canEditEmail = true;
            this.showEmailModal = false;
            this.$nextTick(() => {
                if (this.$refs.emailInput) {
                    this.$refs.emailInput.focus();
                }
            });
        },

        onPhotoSelected(event) {
            const file = event.target.files ? event.target.files[0] : null;
            if (!file) return;

            if (!file.type.match(/^image\//)) {
                alert('Please select an image file (PNG, JPG, WebP).');
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.imageSrc = e.target.result;
                this.showCropModal = true;

                this.$nextTick(() => {
                    const img = this.$refs.cropImage;
                    if (!img) return;

                    if (this.cropper) {
                        this.cropper.destroy();
                        this.cropper = null;
                    }

                    const initCropper = () => {
                        this.cropper = new Cropper(img, {
                            aspectRatio: 1 / 1,
                            viewMode: 2,
                            autoCropArea: 0.85,
                            responsive: true,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: true,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: false,
                            background: false,
                            minContainerHeight: 320,
                            minContainerWidth: 320,
                        });
                    };

                    if (img.complete) {
                        initCropper();
                    } else {
                        img.onload = initCropper;
                    }
                });
            };
            reader.readAsDataURL(file);
        },

        zoomCrop(val) {
            if (this.cropper) this.cropper.zoom(val);
        },

        rotateCrop(deg) {
            if (this.cropper) this.cropper.rotate(deg);
        },

        resetCrop() {
            if (this.cropper) this.cropper.reset();
        },

        closeCropModal() {
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.showCropModal = false;
            this.imageSrc = '';
            if (this.$refs.photoInput) {
                this.$refs.photoInput.value = '';
            }
        },

        saveCrop() {
            if (!this.cropper || this.isProcessingCrop) return;

            this.isProcessingCrop = true;
            try {
                const canvas = this.cropper.getCroppedCanvas({
                    width: 512,
                    height: 512,
                    fillColor: '#ffffff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (!canvas) {
                    alert('Could not generate cropped image.');
                    this.isProcessingCrop = false;
                    return;
                }

                const base64Data = canvas.toDataURL('image/png');

                this.$wire.saveCroppedPhoto(base64Data).then(() => {
                    this.isProcessingCrop = false;
                    this.closeCropModal();
                }).catch(() => {
                    this.isProcessingCrop = false;
                });
            } catch (err) {
                console.error('Cropper error:', err);
                this.isProcessingCrop = false;
                alert('An error occurred while cropping.');
            }
        }
    }"
    @open-password-modal.window="showPasswordModal = true"
    @password-updated.window="showPasswordModal = false"
    @scroll-to-about.window="
        $nextTick(() => {
            const el = document.getElementById('about-profile-card');
            if (el) {
                const y = el.getBoundingClientRect().top + window.pageYOffset - 80;
                window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
            }
        });
    "
    @scroll-to-top.window="
        $nextTick(() => {
            markMainClean();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    "
>
    <!-- Cropper CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.0/cropper.min.css">
    <!-- Cropper.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.0/cropper.min.js"></script>

    @if ($hasError)
        <!-- TOP-LEVEL ERROR BOUNDARY FALLBACK NOTICE -->
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-xs">
            <svg class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold">Section Notice</h3>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    {{ $errorMessage ?? 'We encountered an unexpected error while loading this section. Other sections remain functional. If this persists, please contact support.' }}
                </p>
            </div>
        </div>
    @endif

    <!-- 1. PAGE HEADER & BREADCRUMBS (PARTIAL) -->
    @include('livewire.admin.users.partials.header')

    <!-- 2. QUICK STATUS & ACTION BAR (INDEPENDENT CHILD VOLT COMPONENT) -->
    <livewire:admin.users.partials.status-bar :user="$user" :key="'admin-user-status-bar-'.$user->_id" />

    <!-- 3. MAIN USER PROFILE & ADDRESS FORM (INDEPENDENT CHILD VOLT COMPONENT) -->
    <livewire:admin.users.partials.profile-info :user="$user" :key="'admin-user-profile-info-'.$user->_id" />

    <!-- 4. ABOUT, SKILLS & RESEARCH PROFILE (INDEPENDENT CHILD VOLT COMPONENT) -->
    <livewire:admin.users.partials.about :user="$user" :key="'admin-user-about-'.$user->_id" />

    <!-- 5. WORK EXPERIENCE (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.experience :user="$user" :key="'admin-user-experience-'.$user->_id" />

    <!-- 6. EDUCATION (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.education :user="$user" :key="'admin-user-education-'.$user->_id" />

    <!-- PUBLICATIONS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.publications :user="$user" :key="'admin-user-publications-'.$user->_id" />

    <!-- 7. PROJECTS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.projects :user="$user" :key="'admin-user-projects-'.$user->_id" />

    <!-- 8. CONFERENCES & SEMINARS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.seminars :user="$user" :key="'admin-user-seminars-'.$user->_id" />

    <!-- 9. CERTIFICATES (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.certificates :user="$user" :key="'admin-user-certificates-'.$user->_id" />

    <!-- 10. AWARDS & ACHIEVEMENTS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.awards :user="$user" :key="'admin-user-awards-'.$user->_id" />

    <!-- 11. THESIS GUIDED (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.thesis :user="$user" :key="'admin-user-thesis-'.$user->_id" />

    <!-- 12. PROFESSIONAL MEMBERSHIPS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.memberships :user="$user" :key="'admin-user-memberships-'.$user->_id" />

    <!-- 13. PATENTS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.patents :user="$user" :key="'admin-user-patents-'.$user->_id" />

    <!-- 14. INVITED POSITIONS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.invited-positions :user="$user" :key="'admin-user-invited-positions-'.$user->_id" />

    <!-- 15. ROLE IN RESEARCH JOURNALS (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.journal-roles :user="$user" :key="'admin-user-journal-roles-'.$user->_id" />

    <!-- ADMIN USER MODALS (CHANGE PASSWORD) -->
    <x-admin.users.password-modal />

    <!-- RIGHT-EDGE FLOATING QUICK NAVIGATION SIDE DRAWER -->
    <div
        x-data="{
            openQuickNav: false,
            activeSection: 'main-user-edit-form',
            sections: [
                { id: 'main-user-edit-form', label: 'Personal & Contact', icon: 'fa-user' },
                { id: 'about-profile-card', label: 'About & Skills', icon: 'fa-address-card' },
                { id: 'experience-profile-card', label: 'Work Experience', icon: 'fa-briefcase' },
                { id: 'education-profile-card', label: 'Education', icon: 'fa-graduation-cap' },
                { id: 'admin-publications', label: 'Publications', icon: 'fa-book-open' },
                { id: 'admin-projects-section', label: 'Projects', icon: 'fa-diagram-project' },
                { id: 'admin-user-seminars-card', label: 'Conferences & Seminars', icon: 'fa-chalkboard-user' },
                { id: 'admin-certificates-section', label: 'Certificates', icon: 'fa-certificate' },
                { id: 'admin-awards-section', label: 'Awards & Achievements', icon: 'fa-trophy' },
                { id: 'admin-thesis-guided', label: 'Thesis Guided', icon: 'fa-scroll' },
                { id: 'admin-memberships', label: 'Memberships', icon: 'fa-id-badge' },
                { id: 'admin-user-patents', label: 'Patents', icon: 'fa-lightbulb' },
                { id: 'invited-positions', label: 'Invited Positions', icon: 'fa-user-tie' },
                { id: 'admin-editorial-roles', label: 'Journal Roles', icon: 'fa-book-journal-whills' }
            ],
            jumpToSection(sectionId) {
                this.activeSection = sectionId;
                this.openQuickNav = false;
                this.$nextTick(() => {
                    const el = document.getElementById(sectionId);
                    if (el) {
                        const y = el.getBoundingClientRect().top + window.pageYOffset - 80;
                        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
                        el.classList.add('ring-2', 'ring-brand/60', 'ring-offset-2');
                        setTimeout(() => el.classList.remove('ring-2', 'ring-brand/60', 'ring-offset-2'), 1500);
                    }
                });
            },
            scrollToTop() {
                this.openQuickNav = false;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }"
        @keydown.escape.window="openQuickNav = false"
        @click.outside="openQuickNav = false"
        class="fixed right-0 top-1/2 -translate-y-1/2 z-40 flex items-center"
    >
        <!-- Edge Arrow Toggle Button -->
        <button
            type="button"
            @click="openQuickNav = !openQuickNav"
            :aria-expanded="openQuickNav.toString()"
            title="Quick Section Navigation"
            class="flex items-center justify-center w-9 h-12 rounded-l-xl bg-white dark:bg-[#111c26] border border-r-0 border-zinc-200 dark:border-zinc-700 shadow-lg text-zinc-700 dark:text-zinc-200 hover:bg-brand hover:text-white hover:border-brand transition-all cursor-pointer group"
        >
            <svg
                class="size-4 transition-transform duration-200"
                :class="openQuickNav ? 'rotate-180' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2.2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </button>

        <!-- Slide-Out Quick Navigation Drawer -->
        <div
            x-show="openQuickNav"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-6"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-6"
            class="w-60 max-h-[82vh] flex flex-col bg-white dark:bg-[#111c26] border border-r-0 border-zinc-200 dark:border-zinc-700 rounded-l-2xl shadow-2xl overflow-hidden"
        >
            <div class="px-3.5 py-2.5 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/60 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-brand"></span>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-800 dark:text-zinc-100">Quick Navigation</span>
                </div>
                <button
                    type="button"
                    @click="scrollToTop()"
                    title="Scroll to Top"
                    class="text-[11px] font-semibold text-brand hover:underline cursor-pointer"
                >
                    Top &uarr;
                </button>
            </div>

            <nav class="p-1.5 overflow-y-auto space-y-0.5">
                <template x-for="sec in sections" :key="sec.id">
                    <button
                        type="button"
                        @click="jumpToSection(sec.id)"
                        :class="activeSection === sec.id
                            ? 'bg-brand/10 text-brand dark:bg-brand/20 font-semibold'
                            : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800/70'"
                        class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs transition-colors text-left cursor-pointer"
                    >
                        <i class="fa-solid w-4 text-center text-[11px] opacity-80" :class="sec.icon"></i>
                        <span class="truncate" x-text="sec.label"></span>
                    </button>
                </template>
            </nav>
        </div>
    </div>
</div>
