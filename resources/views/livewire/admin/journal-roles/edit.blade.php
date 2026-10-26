<?php

use App\Models\JournalRole;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Edit Journal Role - Admin')] class extends Component {
    public JournalRole $journalRole;

    #[Validate('required|string|max:255', message: [
        'name.required' => 'The role name is required.',
        'name.max' => 'The role name may not exceed 255 characters.',
    ])]
    public string $name = '';

    #[Validate('required|in:1,0', message: [
        'status.required' => 'The status is required.',
        'status.in' => 'Please select a valid status.',
    ])]
    public string $status = '1';

    public function mount(JournalRole|string $journalRole): void
    {
        if (is_string($journalRole)) {
            $this->journalRole = JournalRole::findOrFail($journalRole);
        } else {
            $this->journalRole = $journalRole;
        }

        $this->name = (string) ($this->journalRole->name ?? '');
        $rawStatus = (string) ($this->journalRole->status ?? '1');
        $this->status = in_array($rawStatus, ['1', 1, 'active', true], true) ? '1' : '0';
    }

    public function updatedName(): void
    {
        $roleName = trim($this->name);
        $roleId = (string) ($this->journalRole->_id ?? $this->journalRole->id);
        if ($roleName !== '' && JournalRole::isNameTaken($roleName, $roleId)) {
            $this->addError('name', "The role name '{$roleName}' already exists. Please enter a unique role name.");
        } else {
            $this->resetErrorBag('name');
        }
    }

    public function save(): void
    {
        $this->validate();

        $roleName = trim($this->name);
        $roleId = (string) ($this->journalRole->_id ?? $this->journalRole->id);

        if (JournalRole::isNameTaken($roleName, $roleId)) {
            $this->addError('name', "The role name '{$roleName}' already exists. Please enter a unique role name.");

            return;
        }

        $this->journalRole->name = $roleName;
        $this->journalRole->status = (int) $this->status;
        $this->journalRole->save();

        session()->flash('message', "Journal role '{$roleName}' updated successfully.");

        $this->redirect(route('admin.journal-roles.index'), navigate: true);
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- TOP HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-zinc-800 dark:hover:text-zinc-200 transition">Dashboard</a>
                <span>/</span>
                <a href="{{ route('admin.journal-roles.index') }}" wire:navigate class="hover:text-zinc-800 dark:hover:text-zinc-200 transition">Journal Roles</a>
                <span>/</span>
                <span class="text-zinc-900 dark:text-white font-medium">Edit Role</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Edit Journal Role
            </h1>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Modify role details and active availability.
            </p>
        </div>

        <div>
            <a 
                href="{{ route('admin.journal-roles.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
            >
                <flux:icon name="arrow-left" class="size-3.5" />
                <span>Back to Roles</span>
            </a>
        </div>
    </div>

    <!-- MAIN FORM CARD -->
    <form wire:submit="save" class="space-y-6">
        <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/40 flex items-center gap-3">
                <div class="size-10 rounded-xl bg-brand/10 border border-brand/20 text-brand flex items-center justify-center shrink-0">
                    <flux:icon name="pencil-square" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">Role Details</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Update role name and operational status.</p>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- GRID: Role Name & Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Role Name (with duplicate protection) -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Role Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            wire:model.blur="name" 
                            placeholder="Enter role name"
                            class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('name') border-rose-400 @enderror"
                        />
                        <p class="text-[11px] text-zinc-400 mt-1">
                            Must be unique. Case-insensitive duplicate checks prevent duplicate role names.
                        </p>
                        @error('name') 
                            <p class="text-rose-500 text-xs mt-1.5 font-semibold flex items-center gap-1.5">
                                <flux:icon name="exclamation-circle" class="size-4 shrink-0" />
                                <span>{{ $message }}</span>
                            </p> 
                        @enderror
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
                        <p class="text-[11px] text-zinc-400 mt-1">
                            Active roles can be selected by reviewers and editors across journals.
                        </p>
                        @error('status') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- FORM ACTIONS FOOTER -->
            <div class="px-6 py-4 bg-zinc-50/80 dark:bg-zinc-900/60 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-end gap-3">
                <a 
                    href="{{ route('admin.journal-roles.index') }}" 
                    wire:navigate 
                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition"
                >
                    Cancel
                </a>
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-semibold bg-brand text-white hover:bg-brand/90 disabled:opacity-50 transition shadow-sm"
                >
                    <span wire:loading.remove wire:target="save">Update Role</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        <svg class="animate-spin size-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Updating...</span>
                    </span>
                </button>
            </div>
        </div>
    </form>
</div>
