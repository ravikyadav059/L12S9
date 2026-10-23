<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Organization;
use App\Models\State;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Edit Institute - Admin')] class extends Component {
    use WithFileUploads;

    public Organization $organization;

    #[Validate('required|string|max:255')]
    public string $organization_name = '';

    #[Validate('required|in:education,corporate')]
    public string $organization_type = '';

    #[Validate('required')]
    public string $country = '';

    #[Validate('required')]
    public string $state = '';

    #[Validate('required')]
    public string $city = '';

    #[Validate('nullable|string|max:20')]
    public string $zipcode = '';

    #[Validate('nullable|string|max:255')]
    public string $affiliated_university = '';

    #[Validate('nullable|string|max:500')]
    public string $website_url = '';

    #[Validate('nullable|image|max:2048')]
    public mixed $logo = null;

    public ?string $existingLogo = null;

    #[Validate('required|in:1,0')]
    public string $status = '1';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    #[Validate('required|string')]
    public string $description = '';

    public function mount(Organization|string $organization): void
    {
        if (is_string($organization)) {
            $this->organization = Organization::findOrFail($organization);
        } else {
            $this->organization = $organization;
        }

        $this->organization_name = (string) ($this->organization->organization_name ?? '');
        $this->organization_type = (string) ($this->organization->organization_type ?? 'education');
        $this->country = (string) ($this->organization->country ?? '');
        $this->state = (string) ($this->organization->state ?? '');
        $this->city = (string) ($this->organization->city ?? '');
        $this->zipcode = (string) ($this->organization->zipcode ?? '');
        $this->affiliated_university = (string) ($this->organization->affiliated_university ?? '');
        $this->website_url = (string) ($this->organization->website_url ?? '');
        $this->existingLogo = $this->organization->logo ?? null;

        $rawStatus = (string) ($this->organization->status ?? '1');
        $this->status = in_array($rawStatus, ['1', 1, 'active', true], true) ? '1' : '0';

        $this->address = (string) ($this->organization->address ?? '');
        $this->description = (string) ($this->organization->description ?? '');
    }

    public function updatedCountry(): void
    {
        $this->state = '';
        $this->city = '';
    }

    public function updatedState(): void
    {
        $this->city = '';
    }

    public function getStatesList(): array
    {
        return State::getCachedByCountry($this->country);
    }

    public function getCitiesList(): array
    {
        return City::getCachedByState($this->state);
    }

    public function removeExistingLogo(): void
    {
        $this->existingLogo = null;
    }

    public function save(): void
    {
        $this->validate();

        $logoPath = $this->existingLogo;
        if ($this->logo) {
            $ext = $this->logo->getClientOriginalExtension() ?: 'png';
            $filename = uniqid().'.'.$ext;
            $destination = public_path('uploads');
            File::ensureDirectoryExists($destination);
            $this->logo->move($destination, $filename);
            $logoPath = 'uploads/'.$filename;
            $this->existingLogo = $logoPath;
            $this->logo = null;
        }

        $this->organization->organization_name = trim($this->organization_name);
        $this->organization->organization_type = $this->organization_type;
        $this->organization->country = (string) $this->country;
        $this->organization->state = (string) $this->state;
        $this->organization->city = (string) $this->city;
        $this->organization->zipcode = trim($this->zipcode);
        $this->organization->affiliated_university = trim($this->affiliated_university);
        $this->organization->website_url = trim($this->website_url);
        $this->organization->logo = $logoPath;
        $this->organization->status = (string) $this->status;
        $this->organization->address = trim($this->address);
        $this->organization->description = trim($this->description);
        $this->organization->save();

        session()->flash('message', "Institute '{$this->organization->organization_name}' has been updated successfully!");
    }

    public function with(): array
    {
        return [
            'countries' => Country::getCachedAll(),
            'states' => $this->getStatesList(),
            'cities' => $this->getCitiesList(),
        ];
    }
};
?>

<div class="space-y-6 w-full">
    <!-- BREADCRUMBS & TOP BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-brand transition flex items-center gap-1 font-medium">
                <flux:icon name="home" class="size-3.5" />
                <span>Admin</span>
            </a>
            <span>/</span>
            <a href="{{ route('admin.institutes.index') }}" wire:navigate class="hover:text-brand transition font-medium">
                Institutes
            </a>
            <span>/</span>
            <span class="font-bold text-zinc-900 dark:text-white truncate max-w-[200px]">Edit: {{ $organization->organization_name }}</span>
        </div>

        <div class="flex items-center gap-3">
            @if($organization->slug)
                <a 
                    href="{{ route('institution.show', $organization->slug) }}" 
                    target="_blank" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition"
                >
                    <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                    <span>View Public Page</span>
                </a>
            @endif

            <a 
                href="{{ route('admin.institutes.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition"
            >
                <flux:icon name="arrow-left" class="size-3.5" />
                <span>All Institutes</span>
            </a>
        </div>
    </div>

    <!-- FLASH SUCCESS MESSAGE -->
    @if(session()->has('message'))
        <div class="p-4 sm:p-5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs font-semibold shadow-xs">
            <div class="flex items-center gap-2.5">
                <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <a href="{{ route('admin.institutes.index') }}" wire:navigate class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition self-start sm:self-auto">
                Back to Institutes &rarr;
            </a>
        </div>
    @endif

    <!-- MAIN FORM CARD -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <!-- HEADER -->
        <div class="p-6 sm:p-8 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-gradient-to-r from-zinc-50/50 to-white dark:from-zinc-900/40 dark:to-[#111c26]">
            <div class="flex items-center gap-3">
                <div class="size-11 rounded-xl bg-brand/10 border border-brand/20 text-brand flex items-center justify-center shrink-0">
                    <flux:icon name="pencil-square" class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Edit Institute Details</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Modify information, location, logo, and active status for this organization.</p>
                </div>
            </div>
        </div>

        <!-- FORM BODY -->
        <form wire:submit="save" class="p-6 sm:p-8 space-y-6">
            <!-- ROW 1: Institute Name & Type -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Institute Name* -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Institute Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="organization_name" 
                        placeholder="e.g. Virtueteam INC or Harvard University"
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('organization_name') border-rose-400 dark:border-rose-500 @enderror"
                    />
                    @error('organization_name') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Type* -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Type <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        wire:model="organization_type" 
                        class="w-full px-3 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('organization_type') border-rose-400 dark:border-rose-500 @enderror"
                    >
                        <option value="education">Education</option>
                        <option value="corporate">Corporate</option>
                    </select>
                    @error('organization_type') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- ROW 2: Country, State, City (Dynamic Dependent Dropdowns) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4 sm:p-5 rounded-2xl bg-zinc-50/60 dark:bg-zinc-900/40 border border-zinc-200/80 dark:border-zinc-800/80">
                <!-- Country* -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            Country <span class="text-rose-500">*</span>
                        </label>
                        <span wire:loading wire:target="country" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                    </div>
                    <select 
                        wire:model.live="country" 
                        class="w-full px-3 py-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('country') border-rose-400 dark:border-rose-500 @enderror"
                    >
                        <option value="">Select Country</option>
                        @foreach($countries as $c)
                            <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                    @error('country') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- State* -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            State <span class="text-rose-500">*</span>
                        </label>
                        <span wire:loading wire:target="state" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                    </div>
                    <select 
                        wire:model.live="state" 
                        @disabled(empty($country))
                        class="w-full px-3 py-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition disabled:opacity-50 disabled:cursor-not-allowed @error('state') border-rose-400 dark:border-rose-500 @enderror"
                    >
                        <option value="">{{ empty($country) ? 'Select Country First' : 'Select State' }}</option>
                        @foreach($states as $s)
                            <option value="{{ $s['id'] }}">{{ $s['name'] }}</option>
                        @endforeach
                    </select>
                    @error('state') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- City* -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        City <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        wire:model="city" 
                        @disabled(empty($state))
                        class="w-full px-3 py-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition disabled:opacity-50 disabled:cursor-not-allowed @error('city') border-rose-400 dark:border-rose-500 @enderror"
                    >
                        <option value="">{{ empty($state) ? 'Select State First' : 'Select City' }}</option>
                        @foreach($cities as $ct)
                            <option value="{{ $ct['id'] }}">{{ $ct['name'] }}</option>
                        @endforeach
                    </select>
                    @error('city') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- ROW 3: Zip Code, Affiliated University, Website URL -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Zip Code -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Zip Code
                    </label>
                    <input 
                        type="text" 
                        wire:model="zipcode" 
                        placeholder="e.g. 22012"
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('zipcode') border-rose-400 dark:border-rose-500 @enderror"
                    />
                    @error('zipcode') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Affiliated University -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Affiliated University
                    </label>
                    <input 
                        type="text" 
                        wire:model="affiliated_university" 
                        placeholder="e.g. Dell or Cambridge University"
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('affiliated_university') border-rose-400 dark:border-rose-500 @enderror"
                    />
                    @error('affiliated_university') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Website URL -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Website URL
                    </label>
                    <input 
                        type="url" 
                        wire:model="website_url" 
                        placeholder="https://example.com"
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('website_url') border-rose-400 dark:border-rose-500 @enderror"
                    />
                    @error('website_url') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- ROW 4: Status & Institute Logo -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <!-- Status* -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Status <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        wire:model="status" 
                        class="w-full px-3 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('status') border-rose-400 dark:border-rose-500 @enderror"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <p class="text-[11px] text-zinc-400 mt-1">Active institutes appear publicly on the Scholar Portal search.</p>
                    @error('status') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Institute Logo -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Institute Logo
                    </label>
                    <div class="flex items-start gap-4">
                        @if ($logo)
                            <div class="relative size-16 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-zinc-100 dark:bg-zinc-800 p-1 shrink-0">
                                <img src="{{ $logo->temporaryUrl() }}" alt="New Logo Preview" class="w-full h-full object-contain rounded-lg" />
                                <button 
                                    type="button" 
                                    wire:click="$set('logo', null)" 
                                    class="absolute -top-1 -right-1 size-5 bg-rose-500 hover:bg-rose-600 text-white rounded-full flex items-center justify-center text-xs shadow-xs cursor-pointer"
                                    title="Cancel New Upload"
                                >
                                    &times;
                                </button>
                            </div>
                        @elseif ($existingLogo)
                            <div class="relative size-16 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-zinc-100 dark:bg-zinc-800 p-1 shrink-0">
                                <img src="{{ str_starts_with($existingLogo, 'http') ? $existingLogo : asset($existingLogo) }}" alt="Current Logo" class="w-full h-full object-contain rounded-lg" />
                                <button 
                                    type="button" 
                                    wire:click="removeExistingLogo" 
                                    class="absolute -top-1 -right-1 size-5 bg-rose-500 hover:bg-rose-600 text-white rounded-full flex items-center justify-center text-xs shadow-xs cursor-pointer"
                                    title="Remove Existing Logo"
                                >
                                    &times;
                                </button>
                            </div>
                        @else
                            <div class="size-16 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 flex items-center justify-center text-zinc-400 shrink-0">
                                <flux:icon name="photo" class="size-6" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <input 
                                type="file" 
                                wire:model="logo" 
                                accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                                class="block w-full text-xs text-zinc-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand/10 file:text-brand hover:file:bg-brand/20 transition cursor-pointer"
                            />
                            <p class="text-[11px] text-zinc-400 mt-1">Upload a new image to replace current logo. PNG, JPG, WEBP, or SVG up to 2MB.</p>
                            <div wire:loading wire:target="logo" class="text-xs text-brand mt-1 font-medium flex items-center gap-1">
                                <flux:icon name="arrow-path" class="size-3 animate-spin" />
                                <span>Uploading image...</span>
                            </div>
                            @error('logo') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 5: Address -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Address
                </label>
                <input 
                    type="text" 
                    wire:model="address" 
                    placeholder="e.g. 8444 Westpark Dr Ste 900"
                    class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('address') border-rose-400 dark:border-rose-500 @enderror"
                />
                @error('address') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <!-- ROW 6: Description* -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Description <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    wire:model="description" 
                    rows="5"
                    placeholder="Enter comprehensive description, history, mission, or overview of the institute..."
                    class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('description') border-rose-400 dark:border-rose-500 @enderror"
                ></textarea>
                @error('description') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <!-- ACTIONS / SUBMIT -->
            <div class="pt-4 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                <a 
                    href="{{ route('admin.institutes.index') }}" 
                    wire:navigate 
                    class="w-full sm:w-auto px-4 py-2.5 text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl transition cursor-pointer text-center"
                >
                    &larr; Back to Institutes
                </a>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button 
                        type="submit" 
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-2.5 text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] rounded-xl shadow-md shadow-brand/20 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="save">Update Institute</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                            <flux:icon name="arrow-path" class="size-3.5 animate-spin" />
                            <span>Saving Changes...</span>
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
