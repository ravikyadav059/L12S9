<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Designation;
use App\Models\Experience;
use App\Models\Organization;
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

new #[Layout('components.layouts.admin')] #[Title('Edit User - Admin')] class extends Component {
    public User $user;

    public string $profileId = '';

    #[Validate('nullable|string|max:50')]
    public string $prefix = '';

    #[Validate('required|string|max:100', message: 'First name is required.')]
    public string $first_name = '';

    #[Validate('required|string|max:100', message: 'Last name is required.')]
    public string $last_name = '';

    public string $fullname = '';

    #[Validate('nullable|string|max:150')]
    public string $profile_title = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:50')]
    public string $mobile = '';

    public string $countrycode = '+91';

    public string $countryiso2 = '';

    public ?string $photo = null;

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

    #[Validate('nullable|string|max:50')]
    public string $role = '';

    public string $about_us = '';

    public string $skills_input = '';

    public string $research_interests_input = '';

    // Social & Profile Links
    public string $github_url = '';
    public string $google_scholar_url = '';
    public string $linkedin_url = '';
    public string $orcid_url = '';
    public string $public_profile = '';
    public string $youtube_link = '';
    public string $employee_url = '';

    // Direct update status properties
    public string $profile_status = '0';
    public string $verified = '0';
    public string $publisher_status = '0';
    public string $reviewer_status = '0';
    public string $badge_type = '';
    public bool $enable_conference = false;

    // Change password modal
    public bool $showPasswordModal = false;
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(User $user): void
    {
        if (empty($user->unique_id)) {
            abort(404, 'User does not have a unique_id.');
        }

        $this->user = $user;
        $this->profileId = (string) $this->user->unique_id;
        $this->prefix = (string) ($this->user->prefix ?? '');
        $this->first_name = (string) ($this->user->first_name ?? '');
        $this->last_name = (string) ($this->user->last_name ?? '');

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

        // Social Links from ReviewerProfile (zero dump data)
        $this->github_url = (string) ($reviewerProfile?->github_url ?? '');
        $this->google_scholar_url = (string) ($reviewerProfile?->google_scholar_url ?? '');
        $this->linkedin_url = (string) ($reviewerProfile?->linkedin_url ?? '');
        $this->orcid_url = (string) ($reviewerProfile?->orcid_url ?? '');
        $this->public_profile = (string) ($reviewerProfile?->public_profile ?? '');
        $this->youtube_link = (string) ($reviewerProfile?->youtube_link ?? ($reviewerProfile?->youtube_url ?? ''));
        $this->employee_url = (string) ($reviewerProfile?->employee_url ?? '');

        // Initialize status properties
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

    public function updatedFirstName(): void
    {
        $this->fullname = trim($this->first_name . ' ' . $this->last_name);
    }

    public function updatedLastName(): void
    {
        $this->fullname = trim($this->first_name . ' ' . $this->last_name);
    }

    public function updatedCountry(): void
    {
        $this->state = '';
        $this->city = '';
        $this->resetErrorBag(['country', 'state', 'city']);
    }

    public function updatedState(): void
    {
        $this->city = '';
        $this->resetErrorBag(['state', 'city']);
    }

    public function updatedCity(): void
    {
        $this->resetErrorBag(['city']);
    }

    public function getStatesList(): array
    {
        return ! empty($this->country) ? State::getCachedByCountry($this->country) : [];
    }

    public function getCitiesList(): array
    {
        return ! empty($this->state) ? City::getCachedByState($this->state) : [];
    }

    public function with(): array
    {
        return [
            'countries' => Country::getCachedAll(),
            'states' => $this->getStatesList(),
            'cities' => $this->getCitiesList(),
        ];
    }

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

        // Delete previous local photo if present
        if (! empty($this->user->photo) && str_starts_with($this->user->photo, 'uploads/') && File::exists(public_path($this->user->photo))) {
            File::delete(public_path($this->user->photo));
        }

        // Format requested: uploads/profile_1767955788238_1767955791_waPPhL.png
        $filename = 'profile_' . now()->getTimestampMs() . '_' . time() . '_' . Str::random(6) . '.png';
        $fullPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($fullPath, $decoded);

        $relativePath = 'uploads/' . $filename;
        $this->user->photo = $relativePath;
        $this->user->save();

        $this->photo = $relativePath;
        session()->flash('message', 'Profile photo updated successfully.');
    }

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

    public function updateProfileStatus(string $val): void
    {
        $this->profile_status = $val;
        $this->user->status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Profile status updated.');
    }

    public function updateVerifiedUser(string $val): void
    {
        $this->verified = $val;
        $this->user->verified = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Verified user status updated.');
    }

    public function updatePublisherStatus(string $val): void
    {
        $this->publisher_status = $val;
        $this->user->publisher_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Publisher status updated.');
    }

    public function updateReviewerStatus(string $val): void
    {
        $this->reviewer_status = $val;
        $this->user->reviewer_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Reviewer status updated.');
    }

    public function updateBadgeType(string $val): void
    {
        $this->badge_type = $val;
        $this->user->badge_type = $val;
        $this->user->save();
        session()->flash('quick_message', 'Badge type updated.');
    }

    public function updateUserRole(string $val): void
    {
        $this->role = $val;
        $this->user->role = $val;
        $this->user->save();
        session()->flash('quick_message', 'User role updated.');
    }

    public function toggleConference(): void
    {
        $this->enable_conference = ! $this->enable_conference;
        $this->user->enable_conference = $this->enable_conference;
        $this->user->save();
        session()->flash('quick_message', 'Conference settings updated.');
    }

    public function openPasswordModal(): void
    {
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->resetErrorBag();
        $this->showPasswordModal = true;
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->resetErrorBag();
    }

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
            \App\Models\Research::recordUsage($interestTitle);
        }

        session()->flash('about_message', 'About section updated successfully!');
        $this->dispatch('scroll-to-about');
        $this->dispatch('toast', message: 'About section updated successfully!', type: 'success', position: 'bottom');
    }

    /**
     * Get experiences for the user being edited from ReviewerProfile.
     * Admin view shows both active and inactive records.
     *
     * @return list<array<string, mixed>>
     */
    public function getExperiencesProperty(): array
    {
        $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        $expIds = (array) ($reviewerProfile->experience ?? []);
        if (empty($expIds)) {
            return [];
        }

        $records = Experience::whereIn('_id', $expIds)
            ->orderBy('_id', 'desc')
            ->with(['designationRelation', 'organization'])
            ->get();

        $formatted = [];
        foreach ($records as $exp) {
            $designationTitle = $exp->designationRelation?->designation ?? 'Untitled Position';
            $orgName = $exp->organization?->organization_name ?? 'Organization';
            $desigVal = (string) ($exp->designationRelation?->_id ?? $exp->designation ?? '');
            $orgVal = (string) ($exp->organization?->_id ?? $exp->organization_id ?? '');

            // Generate acronym for logo sub
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
                'status' => ($exp->status === 0 || $exp->status === '0' || $exp->status === false) ? 0 : 1,
                'logo_title' => strtoupper($orgName),
                'logo_sub' => substr($acronym, 0, 4),
            ];
        }

        return $formatted;
    }

    /**
     * Save an experience record:
     * - Case-insensitive deduplication and usage promotion for designation & organization.
     * - Atomically pushes new experience ID to ReviewerProfile->experience.
     */
    public function saveExperience(array $payload): void
    {
        $designationTitle = trim((string) ($payload['designation'] ?? ''));
        $orgName = trim((string) ($payload['organization'] ?? ''));
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $isCurrent = (bool) ($payload['is_current'] ?? false);
        $description = trim((string) ($payload['description'] ?? ''));
        $expId = ! empty($payload['id']) ? trim((string) $payload['id']) : null;
        $statusVal = isset($payload['status']) ? (($payload['status'] === 1 || $payload['status'] === '1' || $payload['status'] === true) ? 1 : 0) : 1;

        if ($designationTitle === '') {
            $this->dispatch('toast', message: 'Designation is required.', type: 'danger', position: 'bottom');

            return;
        }

        if ($orgName === '') {
            $this->dispatch('toast', message: 'Organization is required.', type: 'danger', position: 'bottom');

            return;
        }

        if ($startDate === '') {
            $this->dispatch('toast', message: 'Start date is required.', type: 'danger', position: 'bottom');

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

        // Deduplicate and promote designation & organization
        $designationModel = Designation::recordUsage($designationTitle);
        $orgModel = Organization::recordOrFind($orgName);

        if ($expId) {
            $exp = Experience::findOrFail($expId);
        } else {
            $exp = new Experience();
            $exp->user_id = (string) $this->user->_id;
        }

        $exp->status = $statusVal;
        $exp->designation = (string) $designationModel->_id;
        $exp->organization_id = (string) $orgModel->_id;
        $exp->join_date = Experience::formatMonthYear($startDate);
        $exp->end_date = $isCurrent ? null : Experience::formatMonthYear($endDate);
        $exp->current_organization = $isCurrent ? 1 : 0;
        $exp->description = $description;
        $exp->save();

        // Atomically append _id to ReviewerProfile->experience if not present
        $strId = (string) $exp->_id;
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if (! $profile) {
            $profile = ReviewerProfile::create([
                'user_id' => (string) $this->user->_id,
                'unique_id' => (string) ($this->user->unique_id ?? $this->profileId),
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
     * Delete an experience record and atomically remove its ID from ReviewerProfile->experience.
     */
    public function deleteExperience(string $expId): void
    {
        $exp = Experience::find($expId);
        if ($exp) {
            $exp->delete();
        }

        ReviewerProfile::where('user_id', (string) $this->user->_id)->pull('experience', $expId);

        $this->dispatch('toast', message: 'Experience deleted successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }

    /**
     * Toggle experience status between active (1) and inactive (0).
     */
    public function toggleExperienceStatus(string $expId): void
    {
        $exp = Experience::findOrFail($expId);
        $isActive = ($exp->status === 1 || $exp->status === '1' || $exp->status === true || (string) $exp->status === '1');
        $newStatus = $isActive ? 0 : 1;
        $exp->status = $newStatus;
        $exp->save();

        $label = $newStatus === 1 ? 'Active' : 'Inactive';
        $this->dispatch('toast', message: "Experience set to {$label}.", type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
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
    <!-- Cropper CDN  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.0/cropper.min.css">
    <!-- Cropper.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.0/cropper.min.js"></script>
    <!-- PAGE BREADCRUMB & HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('admin.users.index') }}" wire:navigate class="hover:text-brand transition flex items-center gap-1">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Back to Users</span>
                </a>
                <span>/</span>
                <span class="text-zinc-700 dark:text-zinc-300 font-medium">Edit User</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white">
                    Edit User Profile
                </h1>
                <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-brand/10 text-brand border border-brand/20">
                    {{ $profileId }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Update user credentials, profile attributes, and verification status.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if(! empty($user->slug))
                <a 
                    href="{{ route('userscholar', ['slug' => $user->slug]) }}" 
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>View Public Profile</span>
                </a>
            @endif
        </div>
    </div>

    @if (session()->has('message'))
        <div class="px-4 py-3 text-xs font-semibold rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between shadow-xs transition">
            <div class="flex items-center gap-2">
                <svg class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-xs cursor-pointer p-1">✕</button>
        </div>
    @endif

    @if (session()->has('photo_error'))
        <div class="px-4 py-3 text-xs font-semibold rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex items-center justify-between shadow-xs transition">
            <div class="flex items-center gap-2">
                <svg class="size-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>{{ session('photo_error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-800 text-xs cursor-pointer p-1">✕</button>
        </div>
    @endif

    <!-- QUICK STATUS & ACTION BAR (ABOVE CARD) -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl p-6 border border-zinc-200/90 dark:border-zinc-800 shadow-sm space-y-4">
        @if (session()->has('quick_message'))
            <div class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between transition">
                <span>{{ session('quick_message') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-xs cursor-pointer">✕</button>
            </div>
        @endif

        <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
            <!-- Profile Status -->
            <div>
                <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                    Profile Status:
                </label>
                <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                    <button 
                        type="button" 
                        wire:click="updateProfileStatus('0')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $profile_status === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Pending
                    </button>
                    <button 
                        type="button" 
                        wire:click="updateProfileStatus('1')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $profile_status === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Active
                    </button>
                    <button 
                        type="button" 
                        wire:click="updateProfileStatus('2')"
                        class="px-3 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer leading-tight text-center active:scale-95 {{ $profile_status === '2' ? 'bg-amber-600 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Re-Document
                    </button>
                    <button 
                        type="button" 
                        wire:click="updateProfileStatus('3')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $profile_status === '3' ? 'bg-[#e03a45] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Reject
                    </button>
                </div>
            </div>

            <!-- Verified User -->
            <div>
                <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                    Verified User:
                </label>
                <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                    <button 
                        type="button" 
                        wire:click="updateVerifiedUser('0')"
                        class="px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $verified === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        No
                    </button>
                    <button 
                        type="button" 
                        wire:click="updateVerifiedUser('1')"
                        class="px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $verified === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Yes
                    </button>
                </div>
            </div>

            <!-- Publisher Status -->
            <div>
                <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                    Publisher Status:
                </label>
                <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                    <button 
                        type="button" 
                        wire:click="updatePublisherStatus('0')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $publisher_status === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Pending
                    </button>
                    <button 
                        type="button" 
                        wire:click="updatePublisherStatus('1')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $publisher_status === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Accept
                    </button>
                    <button 
                        type="button" 
                        wire:click="updatePublisherStatus('2')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $publisher_status === '2' ? 'bg-[#e03a45] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Reject
                    </button>
                </div>
            </div>

            <!-- Reviewer Status -->
            <div>
                <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                    Reviewer Status:
                </label>
                <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                    <button 
                        type="button" 
                        wire:click="updateReviewerStatus('0')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $reviewer_status === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Pending
                    </button>
                    <button 
                        type="button" 
                        wire:click="updateReviewerStatus('1')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $reviewer_status === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Accept
                    </button>
                    <button 
                        type="button" 
                        wire:click="updateReviewerStatus('2')"
                        class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95 {{ $reviewer_status === '2' ? 'bg-[#e03a45] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40' }}"
                    >
                        Reject
                    </button>
                </div>
            </div>

            <!-- Badge Type -->
            <div>
                <label for="badge_type" class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                    Badge Type:
                </label>
                <div class="relative">
                    <select 
                        name="badge_type" 
                        id="badge_type" 
                        class="profile_badge_type appearance-none h-9.5 pl-3.5 pr-8 bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-xl text-xs font-bold text-zinc-800 dark:text-zinc-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand/30 border border-zinc-300/40 dark:border-zinc-700/40 hover:border-zinc-400 dark:hover:border-zinc-600 transition" 
                        user_id="{{ (string) $user->_id }}"
                        wire:change="updateBadgeType($event.target.value)"
                    >
                        <option value="" @selected($badge_type === '')>Select Badge Type</option>
                        <option value="1" @selected($badge_type === '1')>Researcher</option>
                        <option value="2" @selected($badge_type === '2')>Reviewer</option>
                        <option value="3" @selected($badge_type === '3')>Professional</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-zinc-500">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- User Role (Next to Badge Type) -->
            <div>
                <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                    User Role:
                </label>
                <div class="relative">
                    <select 
                        wire:change="updateUserRole($event.target.value)"
                        class="appearance-none h-9.5 pl-3.5 pr-8 bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-xl text-xs font-bold text-zinc-800 dark:text-zinc-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand/30 border border-zinc-300/40 dark:border-zinc-700/40 hover:border-zinc-400 dark:hover:border-zinc-600 transition min-w-[150px]"
                    >
                        <option value="" @selected($role === '')>Select User Role</option>
                        <option value="scholar" @selected($role === 'scholar')>Scholar</option>
                        <option value="admin" @selected($role === 'admin')>Administrator</option>
                        <option value="publisher" @selected($role === 'publisher')>Publisher</option>
                        <option value="python" @selected($role === 'python')>Python</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-zinc-500">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Change Password Button (pill button with blue border) -->
            <div>
                <button 
                    type="button" 
                    @click="showPasswordModal = true"
                    class="h-9.5 px-5 rounded-full border-2 border-[#1991EB] text-[#1991EB] hover:bg-[#1991EB] hover:text-white dark:hover:bg-[#1991EB] dark:hover:text-white text-xs font-bold transition-all duration-200 cursor-pointer shadow-xs active:scale-95 flex items-center gap-1.5 whitespace-nowrap"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    <span>Change Password</span>
                </button>
            </div>

            <!-- Enable Conference for All Journals -->
            <div class="flex items-center gap-3 h-9.5 px-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs">
                <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                    Enable Conference for All Journals:
                </span>
                <button 
                    type="button" 
                    wire:click="toggleConference"
                    role="switch"
                    aria-checked="{{ $enable_conference ? 'true' : 'false' }}"
                    class="relative inline-flex h-5.5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand/30 {{ $enable_conference ? 'bg-brand' : 'bg-zinc-300 dark:bg-zinc-700' }}"
                >
                    <span class="pointer-events-none inline-block size-4.5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out {{ $enable_conference ? 'translate-x-4.5' : 'translate-x-0' }}"></span>
                </button>
                <span class="text-xs font-bold whitespace-nowrap {{ $enable_conference ? 'text-brand' : 'text-zinc-500' }}">
                    {{ $enable_conference ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
        </div>
    </div>

    <!-- MAIN EDIT FORM -->
    <form id="main-user-edit-form" wire:submit="save" @input="checkMainDirty()" @change="checkMainDirty()" class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <!-- FORM HEADER WITH PROFILE PHOTO MANAGEMENT -->
        <div class="p-6 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left: Avatar Preview + Change / Remove Photo Buttons -->
            <div class="flex items-center gap-5">
                <div class="relative group shrink-0">
                    @php
                        $photoSrc = $photo ? (str_starts_with($photo, 'http') ? $photo : asset($photo)) : $user->avatar;
                    @endphp
                    <img 
                        src="{{ $photoSrc }}" 
                        alt="{{ trim($first_name . ' ' . $last_name) }}" 
                        class="size-20 rounded-2xl object-cover border-2 border-brand/30 shadow-md bg-white dark:bg-zinc-800"
                        onerror="this.onerror=null; this.src='{{ $user->avatar }}';"
                    />

                    <!-- Hidden File Input for Cropper -->
                    <input 
                        type="file" 
                        x-ref="photoInput" 
                        @change="onPhotoSelected($event)" 
                        accept="image/png,image/jpeg,image/jpg,image/webp" 
                        class="hidden" 
                    />
                </div>

                <div class="space-y-2">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <span>{{ trim($first_name . ' ' . $last_name) }}</span>
                            @if(!empty($profile_title))
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-zinc-200/70 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                    {{ $profile_title }}
                                </span>
                            @endif
                        </h3>
                        <p class="text-xs text-zinc-400 font-mono">{{ $user->email }}</p>
                    </div>

                    <!-- Photo Action Buttons -->
                    <div class="flex items-center gap-2 pt-0.5">
                        <button 
                            type="button" 
                            @click="$refs.photoInput.click()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-brand hover:bg-brand-600 transition shadow-xs cursor-pointer active:scale-95"
                        >
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Change Photo</span>
                        </button>

                        @if(! empty($photo))
                            <button 
                                type="button" 
                                wire:click="removePhoto" 
                                wire:confirm="Are you sure you want to remove this profile photo?"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/50 border border-rose-200 dark:border-rose-800 transition shadow-xs cursor-pointer active:scale-95"
                            >
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Remove</span>
                            </button>
                        @endif
                        <span class="text-[11px] text-zinc-400 hidden sm:inline-block">JPG, PNG or WebP. Square face crop.</span>
                    </div>
                </div>
            </div>
            
            <div class="text-left md:text-right">
                <span class="text-[10px] uppercase tracking-wider font-bold text-zinc-400 block">Database _id</span>
                <span class="text-xs font-mono text-zinc-600 dark:text-zinc-400">{{ (string) $user->_id }}</span>
            </div>
        </div>

        <!-- FORM FIELDS -->
        <div class="p-6 space-y-8">
            <!-- 1. PERSONAL INFORMATION -->
            <div class="space-y-4">
                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                        1. Personal & Contact Information
                    </h2>
                </div>

                <!-- Prefix, First Name & Last Name -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                    <div class="sm:col-span-3">
                        <label for="prefix" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Prefix
                        </label>
                        <select 
                            wire:model="prefix" 
                            id="prefix"
                            name="prefix"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 cursor-pointer"
                        >
                            <option value="" @selected(empty($prefix))>Please select Prefix</option>
                            <option value="Dr." @selected($prefix === 'Dr.')>Dr.</option>
                            <option value="Prof." @selected($prefix === 'Prof.')>Prof.</option>
                            <option value="Dr.(Prof.)" @selected($prefix === 'Dr.(Prof.)')>Dr. (Prof.)</option>
                            <option value="Mr." @selected($prefix === 'Mr.')>Mr.</option>
                            <option value="Ms." @selected($prefix === 'Ms.')>Ms.</option>
                            <option value="Miss" @selected($prefix === 'Miss')>Miss</option>
                            <option value="Mrs." @selected($prefix === 'Mrs.')>Mrs.</option>
                            <option value="Shree" @selected($prefix === 'Shree')>Shree</option>
                        </select>
                        @error('prefix') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-4">
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            First Name <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model.live.debounce.250ms="first_name" 
                            placeholder="e.g. Chen-Fu"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('first_name') border-rose-400 @enderror"
                        />
                        @error('first_name') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-5">
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Last Name <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model.live.debounce.250ms="last_name" 
                            placeholder="e.g. Chien"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('last_name') border-rose-400 @enderror"
                        />
                        @error('last_name') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Full Name, Profile Title, Email & Mobile -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                Full Name (Display)
                            </label>
                            <span class="text-[10px] text-zinc-400 font-medium">Combined from First &amp; Last Name</span>
                        </div>
                        <div class="relative">
                            <input 
                                type="text" 
                                value="{{ trim($first_name . ' ' . $last_name) }}"
                                readonly 
                                disabled
                                placeholder="Full Display Name"
                                class="w-full h-10 px-3.5 pr-10 bg-zinc-100/80 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-600 dark:text-zinc-400 cursor-not-allowed select-none transition"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-zinc-400">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Title -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Profile Title
                        </label>
                        <input 
                            type="text" 
                            wire:model="profile_title" 
                            value="{{ $profile_title }}"
                            placeholder="Researcher"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                        />
                        @error('profile_title') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Email Address (Disabled by default, unlock confirmation alert) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                Email Address <span class="text-danger">*</span>
                            </label>

                            <!-- Lock/Unlock Status and Trigger -->
                            <button 
                                type="button" 
                                x-show="!canEditEmail"
                                @click="unlockEmail()"
                                class="text-[11px] font-semibold text-brand hover:text-brand-600 flex items-center gap-1 cursor-pointer transition select-none"
                            >
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span>Change Email</span>
                            </button>
                            <span 
                                x-show="canEditEmail" 
                                x-cloak 
                                class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1 select-none"
                            >
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                </svg>
                                <span>Editable</span>
                            </span>
                        </div>

                        <div class="relative">
                            <input 
                                type="email" 
                                wire:model="email" 
                                x-ref="emailInput"
                                :disabled="!canEditEmail"
                                :readonly="!canEditEmail"
                                @click="if (!canEditEmail) unlockEmail()"
                                placeholder="user@example.com"
                                :class="!canEditEmail ? 'bg-zinc-100/80 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 cursor-pointer select-none' : 'bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                                class="w-full h-10 px-3.5 pr-10 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-zinc-400">
                                <template x-if="!canEditEmail">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </template>
                                <template x-if="canEditEmail">
                                    <svg class="size-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </template>
                            </div>
                        </div>
                        @error('email') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Mobile Number (same as Add Journal) -->
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Mobile <span class="text-danger">*</span>
                        </label>
                        <x-phone-input 
                            wire:model="mobile" 
                            country-code-model="countrycode"
                            placeholder="1234567890" 
                            :has-error="$errors->has('mobile')"
                        />
                        @error('mobile') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- 2. ADDRESS & GEO-LOCATION (SAME AS CREATE JOURNAL) -->
            <div class="space-y-4 pt-2">
                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                        2. Address & Geo-Location
                    </h2>
                </div>

                <div class="space-y-4">
                    <div data-field-wrapper>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Address
                        </label>
                        <input 
                            type="text" 
                            wire:model="address" 
                            placeholder="Street address, building, suite..."
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('address') border-rose-400 @enderror"
                        />
                        @error('address') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Country -->
                        <div data-field-wrapper>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                    Country
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
                            @error('country') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- State -->
                        <div data-field-wrapper>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                    State
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
                                wire:key="user-state-select-{{ $country ?: 'none' }}"
                                :has-error="$errors->has('state')"
                            />
                            @error('state') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- City -->
                        <div data-field-wrapper>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                    City
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
                                wire:key="user-city-select-{{ $state ?: 'none' }}"
                                :has-error="$errors->has('city')"
                            />
                            @error('city') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Postal/Zip Code -->
                        <div data-field-wrapper>
                            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Postal/Zip Code
                            </label>
                            <input 
                                type="text" 
                                wire:model="zipcode" 
                                placeholder="e.g. 10001"
                                class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('zipcode') border-rose-400 @enderror"
                            />
                            @error('zipcode') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM FOOTER -->
        <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-900/40 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <a 
                href="{{ route('admin.users.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 border border-zinc-300 dark:border-zinc-700 transition cursor-pointer shadow-xs active:scale-[0.98]"
            >
                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Cancel</span>
            </a>

            <button 
                type="submit" 
                :disabled="!isMainDirty"
                :class="!isMainDirty ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-brand-600 active:scale-[0.98] shadow-md shadow-brand/20 cursor-pointer'"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand transition"
            >
                <svg wire:loading.remove wire:target="save" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="save" class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="save">Save Changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
    <!-- ABOUT, SKILLS & RESEARCH PROFILE (PARTIAL) -->
    @include('livewire.admin.users.partials.about')

    <!-- WORK EXPERIENCE (PARTIAL) -->
    @include('livewire.admin.users.partials.experience')

    <!-- ADMIN USER MODALS (CROP PHOTO, CHANGE PASSWORD, CHANGE EMAIL) -->
    <x-admin.users.crop-modal />
    <x-admin.users.password-modal />
    <x-admin.users.email-modal :user="$user" />
</div>

