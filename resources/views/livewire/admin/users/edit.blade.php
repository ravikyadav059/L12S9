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
 * This component provides administrative control over a user's account details,
 * profile titles, contact information, geographic location, profile statuses,
 * roles, avatar photo with cropping, about/research interests, and work experiences.
 *
 * View partials included in this component:
 * - partials/header.blade.php        : Breadcrumb, title, Unique ID, public profile link, and session alerts
 * - partials/status-bar.blade.php    : Quick status pill toggles (profile, verified, publisher, reviewer, badge, role, password, conference)
 * - partials/profile-info.blade.php   : Main profile form (avatar management, personal info, address & location cascading selects)
 * - partials/about.blade.php         : About / bio, skills tags, research interests, and social profile links
 * - partials/experience.blade.php    : Independent child Volt component with isolated CRUD, status toggling, and error boundary
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
    // SECTION: Role, Bio & Research Interests Properties
    // =========================================================================

    #[Validate('nullable|string|max:50')]
    public string $role = '';

    public string $about_us = '';

    public string $skills_input = '';

    public string $research_interests_input = '';

    // =========================================================================
    // SECTION: Social & Academic Profile Links
    // =========================================================================

    public string $github_url = '';
    public string $google_scholar_url = '';
    public string $linkedin_url = '';
    public string $orcid_url = '';
    public string $public_profile = '';
    public string $youtube_link = '';
    public string $employee_url = '';

    // =========================================================================
    // SECTION: Quick Status & Administrative Permissions
    // =========================================================================

    public string $profile_status = '0';
    public string $verified = '0';
    public string $publisher_status = '0';
    public string $reviewer_status = '0';
    public string $badge_type = '';
    public bool $enable_conference = false;

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
            $this->role = (string) ($this->user->role ?? '');

            // Load About / Bio, Skills and Research Interests
            $this->about_us = (string) ($reviewerProfile?->about_us ?? $reviewerProfile?->aboutus ?? '');
            $skillsData = $reviewerProfile?->skills_tags ?? $reviewerProfile?->skills ?? [];
            if (is_array($skillsData)) {
                $this->skills_input = implode(', ', $skillsData);
            } else {
                $this->skills_input = (string) $skillsData;
            }

            $researchData = $reviewerProfile?->research_tags ?? $reviewerProfile?->research ?? [];
            if (is_array($researchData)) {
                $this->research_interests_input = implode(', ', $researchData);
            } else {
                $this->research_interests_input = (string) $researchData;
            }

            // Hydrate Social & Profile Links
            $this->github_url = (string) ($reviewerProfile?->github_url ?? '');
            $this->google_scholar_url = (string) ($reviewerProfile?->google_scholar_url ?? '');
            $this->linkedin_url = (string) ($reviewerProfile?->linkedin_url ?? '');
            $this->orcid_url = (string) ($reviewerProfile?->orcid_url ?? '');
            $this->public_profile = (string) ($reviewerProfile?->public_profile ?? '');
            $this->youtube_link = (string) ($reviewerProfile?->youtube_link ?? ($reviewerProfile?->youtube_url ?? ''));
            $this->employee_url = (string) ($reviewerProfile?->employee_url ?? '');
        } catch (\Throwable $e) {
            report($e);
        }

        // Initialize status properties with normalized values
        $this->profile_status = (string) $this->user->getStatusCode();
        $this->verified = ! empty($this->user->verified) ? '1' : '0';
        $rawPub = strtolower(trim((string) ($this->user->publisher_status ?? '0')));
        $this->publisher_status = match ($rawPub) {
            '1', 'accept', 'accepted' => '1',
            '2', 'reject', 'rejected' => '2',
            default => '0',
        };

        $rawRev = strtolower(trim((string) ($this->user->reviewer_status ?? '0')));
        $this->reviewer_status = match ($rawRev) {
            '1', 'accept', 'accepted' => '1',
            '2', 'reject', 'rejected' => '2',
            default => '0',
        };
        $this->badge_type = (string) ($this->user->badge_type ?? '');
        $this->enable_conference = (bool) ($this->user->enable_conference ?? false);
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
    // SECTION: Quick Status Bar Direct Actions
    // =========================================================================

    /**
     * Directly update user profile status (0=Pending, 1=Active, 2=Re-Document, 3=Reject).
     */
    public function updateProfileStatus(string $val): void
    {
        $this->profile_status = $val;
        $this->user->status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Profile status updated.');
    }

    /**
     * Directly update verified user status flag (0=No, 1=Yes).
     */
    public function updateVerifiedUser(string $val): void
    {
        $this->verified = $val;
        $this->user->verified = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Verified user status updated.');
    }

    /**
     * Directly update publisher application status (0=Pending, 1=Accept, 2=Reject).
     */
    public function updatePublisherStatus(string $val): void
    {
        $this->publisher_status = $val;
        $this->user->publisher_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Publisher status updated.');
    }

    /**
     * Directly update reviewer application status (0=Pending, 1=Accept, 2=Reject).
     */
    public function updateReviewerStatus(string $val): void
    {
        $this->reviewer_status = $val;
        $this->user->reviewer_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Reviewer status updated.');
    }

    /**
     * Directly update badge type (1=Researcher, 2=Reviewer, 3=Professional).
     */
    public function updateBadgeType(string $val): void
    {
        $this->badge_type = $val;
        $this->user->badge_type = $val;
        $this->user->save();
        session()->flash('quick_message', 'Badge type updated.');
    }

    /**
     * Directly update system user role (scholar, admin, publisher, python).
     */
    public function updateUserRole(string $val): void
    {
        $this->role = $val;
        $this->user->role = $val;
        $this->user->save();
        session()->flash('quick_message', 'User role updated.');
    }

    /**
     * Toggle the conference participation access flag for all journals.
     */
    public function toggleConference(): void
    {
        $this->enable_conference = ! $this->enable_conference;
        $this->user->enable_conference = $this->enable_conference;
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

    // =========================================================================
    // SECTION: About / Bio, Skills & Research Interests Persistence
    // =========================================================================

    /**
     * Validate and save user bio, skills tags, research interests, and social profile links.
     * Automatically registers and tracks research interests in the Research collection.
     */
    public function saveAboutProfile(): void
    {
        $this->validate([
            'about_us' => 'nullable|string|max:3000',
            'skills_input' => 'nullable|string',
            'research_interests_input' => 'nullable|string',
            'github_url' => 'nullable|string|max:255',
            'google_scholar_url' => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|string|max:255',
            'orcid_url' => 'nullable|string|max:255',
            'public_profile' => 'nullable|string|max:255',
            'youtube_link' => 'nullable|string|max:255',
            'employee_url' => 'nullable|string|max:255',
        ]);

        $reviewerProfile = $this->user->reviewerProfile ?? $this->user->reviewerProfile()->make([
            'unique_id' => (string) ($this->user->unique_id ?? $this->profileId),
            'status' => (string) $this->user->getStatusCode(),
        ]);

        $reviewerProfile->about_us = trim($this->about_us);
        $reviewerProfile->aboutus = trim($this->about_us);

        $parsedSkills = array_values(array_filter(array_map('trim', explode(',', $this->skills_input))));
        $reviewerProfile->skills_tags = $parsedSkills;
        $reviewerProfile->skills = $parsedSkills;

        $parsedResearch = array_values(array_filter(array_map('trim', explode(',', $this->research_interests_input))));
        $reviewerProfile->research_tags = $parsedResearch;
        $reviewerProfile->research = $parsedResearch;

        // Save Social Links in ReviewerProfile
        $reviewerProfile->github_url = trim($this->github_url);
        $reviewerProfile->google_scholar_url = trim($this->google_scholar_url);
        $reviewerProfile->linkedin_url = trim($this->linkedin_url);
        $reviewerProfile->orcid_url = trim($this->orcid_url);
        $reviewerProfile->public_profile = trim($this->public_profile);
        $reviewerProfile->youtube_link = trim($this->youtube_link);
        $reviewerProfile->employee_url = trim($this->employee_url);

        $reviewerProfile->save();

        // Track and auto-promote research tags in Research collection
        foreach ($parsedResearch as $interestTitle) {
            Research::recordUsage($interestTitle);
        }

        session()->flash('about_message', 'About section updated successfully!');
        $this->dispatch('scroll-to-about');
        $this->dispatch('toast', message: 'About section updated successfully!', type: 'success', position: 'bottom');
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

    <!-- 2. QUICK STATUS & ACTION BAR (PARTIAL) -->
    @include('livewire.admin.users.partials.status-bar')

    <!-- 3. MAIN USER PROFILE & ADDRESS FORM (PARTIAL) -->
    @include('livewire.admin.users.partials.profile-info')

    <!-- 4. ABOUT, SKILLS & RESEARCH PROFILE (PARTIAL) -->
    @include('livewire.admin.users.partials.about')

    <!-- 5. WORK EXPERIENCE (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.experience :user="$user" :key="'admin-user-experience-'.$user->_id" />

    <!-- 6. EDUCATION (INDEPENDENT CHILD VOLT COMPONENT WITH ERROR BOUNDARY) -->
    <livewire:admin.users.partials.education :user="$user" :key="'admin-user-education-'.$user->_id" />

    <!-- ADMIN USER MODALS (CROP PHOTO, CHANGE PASSWORD, CHANGE EMAIL) -->
    <x-admin.users.crop-modal />
    <x-admin.users.password-modal />
    <x-admin.users.email-modal :user="$user" />
</div>
