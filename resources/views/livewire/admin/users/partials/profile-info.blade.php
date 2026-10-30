{{-- resources/views/livewire/admin/users/partials/profile-info.blade.php --}}
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
