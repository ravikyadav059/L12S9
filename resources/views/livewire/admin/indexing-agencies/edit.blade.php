<?php

use App\Models\IndexingAgency;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Edit Indexing Agency - Admin')] class extends Component {
    use WithFileUploads;

    public IndexingAgency $agency;

    public int $serial_number = 1;

    #[Validate('required|string|max:255')]
    public string $agency_name = '';

    #[Validate('required|in:1,0')]
    public string $status = '1';

    public ?string $existingImage = null;

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp,svg,gif|max:2048', message: [
        'image.image' => 'The agency icon must be a valid image file.',
        'image.max' => 'The agency icon may not be greater than 2MB.',
    ])]
    public mixed $image = null;

    public function mount(IndexingAgency|string $agency): void
    {
        if (is_string($agency)) {
            $this->agency = IndexingAgency::findOrFail($agency);
        } else {
            $this->agency = $agency;
        }

        $this->serial_number = (int) ($this->agency->serial_number ?? 1);
        $this->agency_name = (string) ($this->agency->agency_name ?? '');

        $rawStatus = (string) ($this->agency->status ?? '1');
        $this->status = in_array($rawStatus, ['1', 1, 'active', true], true) ? '1' : '0';

        $this->existingImage = $this->agency->image_url;
    }

    public function updatedAgencyName(): void
    {
        $name = trim($this->agency_name);
        if ($name !== '' && IndexingAgency::isNameTaken($name, (string) $this->agency->_id)) {
            $this->addError('agency_name', "The agency name '{$name}' is already registered. Please choose a unique name.");
        } else {
            $this->resetErrorBag('agency_name');
        }
    }

    public function save(): void
    {
        $this->validate();

        $name = trim($this->agency_name);
        if (IndexingAgency::isNameTaken($name, (string) $this->agency->_id)) {
            $this->addError('agency_name', "The agency name '{$name}' is already registered. Please choose a unique name.");

            return;
        }

        $this->agency->agency_name = $name;
        $this->agency->status = (int) $this->status;

        // If serial number is zero or duplicates another record, ensure unique
        if ($this->serial_number <= 0 || IndexingAgency::isSerialNumberTaken($this->serial_number, (string) $this->agency->_id)) {
            $this->serial_number = IndexingAgency::generateUniqueSerialNumber((string) $this->agency->_id);
        }
        $this->agency->serial_number = (int) $this->serial_number;

        // Handle replacement image upload if provided
        if ($this->image) {
            $dir = public_path('uploads/indexingAgency');
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // Remove previous local file if applicable
            if ($this->agency->image && ! str_starts_with($this->agency->image, 'http') && File::exists(public_path($this->agency->image))) {
                @unlink(public_path($this->agency->image));
            }

            $cleanSlug = Str::slug($name) ?: 'agency';
            $filename = $cleanSlug.'_'.time().'.'.$this->image->getClientOriginalExtension();
            $this->image->move($dir, $filename);
            $this->agency->image = 'uploads/indexingAgency/'.$filename;
        }

        $this->agency->save();

        session()->flash('message', "Indexing Agency '{$this->agency->agency_name}' updated successfully.");
        $this->redirect(route('admin.indexing-agencies.index'), navigate: true);
    }
};
?>

<div class="space-y-6 w-full">
    <!-- TOP HEADER & BREADCRUMBS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-zinc-200/80 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-brand transition">Admin</a>
                <span>/</span>
                <a href="{{ route('admin.indexing-agencies.index') }}" wire:navigate class="hover:text-brand transition">Indexing Agencies</a>
                <span>/</span>
                <span class="font-bold text-zinc-900 dark:text-white">Edit: {{ $agency->agency_name }}</span>
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="pencil-square" class="size-6 text-brand" />
                <span>Edit Indexing Agency</span>
            </h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                Update agency details, active availability, or branding icons.
            </p>
        </div>

        <div>
            <a 
                href="{{ route('admin.indexing-agencies.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
            >
                <flux:icon name="arrow-left" class="size-3.5" />
                <span>Back to Directory</span>
            </a>
        </div>
    </div>

    <!-- MAIN FORM CARD -->
    <form wire:submit="save" class="space-y-6">
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-brand/10 border border-brand/20 text-brand flex items-center justify-center shrink-0">
                        <flux:icon name="tag" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ $agency->agency_name }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">ID: {{ (string) $agency->_id }}</p>
                    </div>
                </div>

                <div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ (int)$agency->status === 1 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20' }}">
                        {{ (int)$agency->status === 1 ? 'Active Record' : 'Inactive Record' }}
                    </span>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- GRID: Serial Number & Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Serial Number (Prefilled & Readonly) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Serial Number <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1">
                                <flux:icon name="lock-closed" class="size-3" />
                                <span>Assigned & Locked</span>
                            </span>
                        </div>
                        <div class="relative">
                            <input 
                                type="text" 
                                value="#{{ $serial_number }}" 
                                readonly 
                                disabled
                                class="w-full px-4 py-2.5 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm font-mono font-bold text-zinc-700 dark:text-zinc-300 cursor-not-allowed select-none"
                            />
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-1">
                            Unique sequence identifier. Protected against duplicate assignment.
                        </p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Status <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            wire:model="status" 
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('status') border-rose-400 @enderror"
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        @error('status') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Agency Name (with duplicate protection) -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Agency Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model.blur="agency_name" 
                        placeholder="e.g. Scopus, Web of Science, PubMed, Google Scholar..."
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('agency_name') border-rose-400 @enderror"
                    />
                    <p class="text-[11px] text-zinc-400 mt-1">
                        Must be unique across all indexing agencies.
                    </p>
                    @error('agency_name') 
                        <p class="text-rose-500 text-xs mt-1.5 font-semibold flex items-center gap-1.5">
                            <flux:icon name="exclamation-circle" class="size-4 shrink-0" />
                            <span>{{ $message }}</span>
                        </p> 
                    @enderror
                </div>

                <!-- Agency Icon & Current / Preview Section -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Agency Icon
                    </label>

                    <div class="p-5 rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/30 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start gap-6">
                            <!-- Current / Preview Previews Container -->
                            <div class="flex items-center gap-4 shrink-0">
                                <!-- 1. Current Icon -->
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                        Current Icon:
                                    </span>
                                    <div class="size-20 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2 flex items-center justify-center overflow-hidden shadow-2xs">
                                        @if(!empty($existingImage))
                                            <img 
                                                src="{{ $existingImage }}" 
                                                alt="Current Agency Icon" 
                                                class="w-full h-full object-contain"
                                                onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'text-[10px] text-zinc-400\'>N/A</span>';"
                                            />
                                        @else
                                            <flux:icon name="photo" class="size-6 text-zinc-400 opacity-50" />
                                        @endif
                                    </div>
                                </div>

                                <!-- 2. New Upload Preview (if selected) -->
                                @if ($image)
                                    <div class="flex items-center text-zinc-300 dark:text-zinc-600">
                                        <flux:icon name="arrow-right" class="size-4" />
                                    </div>

                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="text-[10px] font-bold text-brand uppercase tracking-wider">
                                            New Preview:
                                        </span>
                                        <div class="relative size-20 rounded-2xl border-2 border-brand/40 bg-white dark:bg-zinc-800 p-2 flex items-center justify-center overflow-hidden shadow-xs">
                                            <img src="{{ $image->temporaryUrl() }}" alt="New Icon Preview" class="w-full h-full object-contain" />
                                            <button 
                                                type="button" 
                                                wire:click="$set('image', null)" 
                                                class="absolute top-1 right-1 size-4 bg-rose-500 text-white rounded-full flex items-center justify-center text-[10px] shadow-xs hover:bg-rose-600 transition cursor-pointer"
                                                title="Cancel new icon"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Upload Inputs -->
                            <div class="flex-1 space-y-2">
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 block">
                                    {{ $existingImage ? 'Replace with a new icon (Optional):' : 'Upload an agency icon:' }}
                                </span>
                                <input 
                                    type="file" 
                                    wire:model="image" 
                                    accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml,image/gif"
                                    class="block w-full text-xs text-zinc-500 dark:text-zinc-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand file:text-white hover:file:bg-brand-600 transition cursor-pointer"
                                />
                                <div class="text-[11px] text-zinc-400 space-y-0.5">
                                    <p>&bull; Leave empty to keep the existing icon unchanged.</p>
                                    <p>&bull; Supported formats: PNG, JPG, JPEG, WEBP, SVG, GIF (Max: 2MB).</p>
                                </div>

                                <div wire:loading wire:target="image" class="text-xs text-brand font-semibold flex items-center gap-1.5 pt-1">
                                    <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span>Uploading & generating preview...</span>
                                </div>

                                @error('image') 
                                    <p class="text-rose-500 text-xs mt-1 font-semibold">{{ $message }}</p> 
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER BUTTONS -->
            <div class="p-5 sm:p-6 border-t border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center justify-end gap-3">
                <a 
                    href="{{ route('admin.indexing-agencies.index') }}" 
                    wire:navigate 
                    class="px-4 py-2.5 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition cursor-pointer"
                >
                    Cancel
                </a>

                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="px-5 py-2.5 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs transition inline-flex items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-1.5">
                        <flux:icon name="check" class="size-4" />
                        <span>Update Indexing Agency</span>
                    </span>
                    <span wire:loading.inline-flex wire:target="save" class="inline-flex items-center gap-1.5">
                        <svg class="animate-spin size-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Updating...</span>
                    </span>
                </button>
            </div>
        </div>
    </form>
</div>
