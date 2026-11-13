<?php

use App\Models\City;
use App\Models\Country;
use App\Models\ReviewerProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

/**
 * Admin User Edit - Personal Information & Address (Isolated Volt Child Component)
 */
new class extends Component {
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

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->profileId = (string) ($this->user->unique_id ?? '');
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

        try {
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

        if (! empty($this->user->photo) && str_starts_with($this->user->photo, 'uploads/') && File::exists(public_path($this->user->photo))) {
            File::delete(public_path($this->user->photo));
        }

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
    x-data="{
        showCropModal: false,
        showEmailModal: false,
        canEditEmail: false,
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

    <!-- MAIN USER EDIT FORM: AVATAR MANAGEMENT, PERSONAL & CONTACT INFO, AND ADDRESS -->
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
                            <option value="">Please select Prefix</option>
                            <option value="Prof." @selected($prefix === 'Prof.')>Prof.</option>
                            <option value="Dr." @selected($prefix === 'Dr.')>Dr.</option>
                            <option value="Dr. (Prof.)" @selected($prefix === 'Dr. (Prof.)')>Dr. (Prof.)</option>
                            <option value="Mr." @selected($prefix === 'Mr.')>Mr.</option>
                            <option value="Ms." @selected($prefix === 'Ms.')>Ms.</option>
                            <option value="Mrs." @selected($prefix === 'Mrs.')>Mrs.</option>
                            <option value="Shree" @selected($prefix === 'Shree')>Shree</option>
                        </select>
                        @error('prefix') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-4">
                        <label for="first_name" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            First Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="first_name"
                            wire:model.live.debounce.250ms="first_name" 
                            placeholder="e.g. John"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('first_name') border-rose-400 @enderror"
                        />
                        @error('first_name') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-5">
                        <label for="last_name" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Last Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="last_name"
                            wire:model.live.debounce.250ms="last_name" 
                            placeholder="e.g. Doe"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('last_name') border-rose-400 @enderror"
                        />
                        @error('last_name') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Combined Full Name Preview & Profile Title (Designation) -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                    <div class="sm:col-span-6">
                        <label for="fullname" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Full Name Preview
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="fullname"
                                value="{{ trim(($prefix ? $prefix . ' ' : '') . $fullname) }}" 
                                readonly 
                                class="w-full h-10 px-3.5 bg-zinc-100 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-800 rounded-xl text-xs text-zinc-600 dark:text-zinc-400 cursor-not-allowed font-medium"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-zinc-400">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-1">Computed automatically from prefix, first name, and last name.</p>
                    </div>

                    <div class="sm:col-span-6">
                        <label for="profile_title" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Profile Title (Designation)
                        </label>
                        <input 
                            type="text" 
                            id="profile_title"
                            wire:model="profile_title" 
                            placeholder="e.g. Associate Professor / Senior Researcher"
                            class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('profile_title') border-rose-400 @enderror"
                        />
                        @error('profile_title') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Email & Mobile Number -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                    <!-- Email with security modal confirmation lock -->
                    <div class="sm:col-span-6">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="email" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                Email Address <span class="text-rose-500">*</span>
                            </label>
                            
                            <!-- Lock/Unlock Status and Trigger -->
                            <div>
                                <template x-if="!canEditEmail">
                                    <button 
                                        type="button" 
                                        @click="unlockEmail()"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline cursor-pointer"
                                    >
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <span>Unlock to Change</span>
                                    </button>
                                </template>
                                <template x-if="canEditEmail">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                        </svg>
                                        <span>Unlocked for Edit</span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="relative">
                            <input 
                                type="email" 
                                id="email"
                                x-ref="emailInput"
                                wire:model="email" 
                                :readonly="!canEditEmail"
                                :class="canEditEmail ? 'bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 cursor-text' : 'bg-zinc-100 dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 cursor-not-allowed'"
                                class="w-full h-10 px-3.5 rounded-xl text-xs text-zinc-900 dark:text-white border focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('email') border-rose-400 @enderror"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-zinc-400">
                                <template x-if="!canEditEmail">
                                    <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </template>
                            </div>
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-1">Changing email affects login credentials and requires confirmation.</p>
                        @error('email') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Mobile Number -->
                    <div class="sm:col-span-6">
                        <label for="mobile" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Mobile Number <span class="text-rose-500">*</span>
                        </label>
                        <x-phone-input 
                            name="mobile" 
                            wire:model="mobile" 
                            country-code-name="countrycode"
                            country-code-model="countrycode"
                            :country-code-value="$countrycode"
                            :default-country="!empty($countryiso2) ? strtolower($countryiso2) : 'in'"
                            placeholder="Mobile number"
                            :has-error="$errors->has('mobile')"
                        />
                        @error('mobile') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- 2. ADDRESS & GEO-LOCATION INFORMATION -->
            <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                        2. Address &amp; Geo-Location
                    </h2>
                </div>

                <!-- Street Address -->
                <div>
                    <label for="address" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Street Address / Affiliation Office
                    </label>
                    <textarea 
                        id="address"
                        wire:model="address" 
                        rows="2" 
                        placeholder="e.g. Department of Computer Science, University Campus, Building 4"
                        class="w-full px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('address') border-rose-400 @enderror"
                    ></textarea>
                    @error('address') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Cascading Geo-Location: Country, State, City, Zipcode -->
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                    <!-- Country -->
                    <div class="sm:col-span-3">
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
                            size="default"
                            :has-error="$errors->has('country')"
                        />
                        @error('country') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- State (Cascades from Country) -->
                    <div class="sm:col-span-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                State / Province
                            </label>
                            <span wire:loading wire:target="state" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                        </div>
                        <x-searchable-select 
                            wire:model.live="state" 
                            :options="$states" 
                            placeholder="Select State" 
                            search-placeholder="Search state..." 
                            disabled-placeholder="Select country first"
                            :disabled="empty($country)"
                            size="default"
                            wire:key="user-state-select-{{ $country ?: 'none' }}"
                            :has-error="$errors->has('state')"
                        />
                        @error('state') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- City (Cascades from State) -->
                    <div class="sm:col-span-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                City
                            </label>
                            <span wire:loading wire:target="city" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                        </div>
                        <x-searchable-select 
                            wire:model="city" 
                            :options="$cities" 
                            placeholder="Select City" 
                            search-placeholder="Search city..." 
                            disabled-placeholder="Select state first"
                            :disabled="empty($state)"
                            size="default"
                            wire:key="user-city-select-{{ $state ?: 'none' }}"
                            :has-error="$errors->has('city')"
                        />
                        @error('city') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Zipcode -->
                    <div class="sm:col-span-3">
                        <label for="zipcode" class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Zip / Postal Code
                        </label>
                        <input 
                            id="zipcode"
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

    <!-- ADMIN USER MODALS (CROP PHOTO, CHANGE EMAIL) -->
    <x-admin.users.crop-modal />
    <x-admin.users.email-modal :user="$user" />
</div>
