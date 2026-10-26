<?php

use App\Models\JournalRole;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Journal Roles - Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function toggleStatus(string $id): void
    {
        $role = JournalRole::find($id);
        if ($role) {
            $currentStatus = (int) ($role->status ?? 0);
            $role->status = $currentStatus === 1 ? 0 : 1;
            $role->save();

            session()->flash('message', "Status for role '{$role->name}' updated successfully.");
        }
    }

    public function deleteRole(string $id): void
    {
        $role = JournalRole::find($id);
        if ($role) {
            $name = $role->name;
            $role->delete();

            session()->flash('message', "Journal Role '{$name}' was deleted successfully.");
        }
    }

    public function deleteSelected(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        JournalRole::whereIn('_id', $ids)->delete();
        session()->flash('message', count($ids).' role(s) deleted successfully.');
    }

    public function toggleSelectedStatus(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        $roles = JournalRole::whereIn('_id', $ids)->get();
        foreach ($roles as $r) {
            $currentStatus = (int) ($r->status ?? 0);
            $r->status = $currentStatus === 1 ? 0 : 1;
            $r->save();
        }

        session()->flash('message', 'Status toggled for '.count($ids).' role(s).');
    }

    public function with(): array
    {
        $query = JournalRole::query();

        $searchClean = trim($this->search);
        if ($searchClean !== '') {
            $regex = new \MongoDB\BSON\Regex(preg_quote($searchClean, '/'), 'i');
            if (preg_match('/^[a-f\d]{24}$/i', $searchClean)) {
                $query->where(function ($q) use ($searchClean, $regex) {
                    try {
                        $q->orWhere('_id', new \MongoDB\BSON\ObjectId($searchClean))
                            ->orWhere('_id', $searchClean);
                    } catch (\Throwable) {
                        $q->orWhere('_id', $searchClean);
                    }
                    $q->orWhere('name', 'regex', $regex);
                });
            } else {
                $query->where('name', 'regex', $regex);
            }
        }

        if ($this->statusFilter === 'active') {
            $query->whereIn('status', [1, '1', true]);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where(function ($q) {
                $q->whereIn('status', [0, '0', false])
                    ->orWhereNull('status');
            });
        }

        if ($this->sortField === 'status') {
            $query->orderBy('status', $this->sortDirection);
        } elseif ($this->sortField === 'created_at') {
            $query->orderBy('created_at', $this->sortDirection);
        } else {
            $query->orderBy('name', $this->sortDirection);
        }

        /** @var LengthAwarePaginator $roles */
        $roles = $query->paginate($this->perPage);

        return [
            'roles' => $roles,
            'currentPageIds' => $roles->pluck('_id')->map(fn ($id) => (string) $id)->values()->toArray(),
        ];
    }
};
?>

<div 
    x-data="{
        selectedRows: [],
        selectAll: false,
        pageIds: @js($currentPageIds),
        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedRows = Array.from(new Set([...this.selectedRows, ...this.pageIds]));
            } else {
                this.selectedRows = this.selectedRows.filter(id => !this.pageIds.includes(id));
            }
        },
        updateHeaderCheckbox() {
            this.selectAll = this.pageIds.length > 0 && this.pageIds.every(id => this.selectedRows.includes(id));
        }
    }"
    x-init="$watch('pageIds', value => updateHeaderCheckbox())"
    class="space-y-6 w-full"
>
    <!-- TOP HEADER & BREADCRUMBS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-zinc-200/80 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-brand transition">Admin</a>
                <span>/</span>
                <span class="font-bold text-zinc-900 dark:text-white">Journal Roles</span>
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="briefcase" class="size-6 text-brand" />
                <span>Journal Roles Directory</span>
            </h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                Manage editorial and reviewer role designations across journals.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a 
                href="{{ route('admin.journal-roles.create') }}" 
                wire:navigate 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 shadow-xs transition cursor-pointer"
            >
                <flux:icon name="plus" class="size-4" />
                <span>Add New Role</span>
            </a>
        </div>
    </div>

    <!-- FLASH SUCCESS MESSAGE -->
    @if(session()->has('message'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 flex items-center justify-between gap-3 text-xs font-semibold shadow-xs">
            <div class="flex items-center gap-2.5">
                <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-base leading-none">&times;</button>
        </div>
    @endif

    <!-- BULK ACTIONS FLOATING BAR -->
    <div 
        x-show="selectedRows.length > 0" 
        x-cloak 
        x-transition
        class="flex flex-wrap items-center justify-between gap-3 bg-zinc-900 text-white px-4 py-3 rounded-2xl shadow-xl border border-zinc-700/60"
    >
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center px-2.5 py-1 text-xs font-bold bg-brand text-white rounded-lg">
                <span x-text="selectedRows.length"></span> Selected
            </span>
            <button 
                type="button" 
                @click="selectedRows = []; selectAll = false" 
                class="text-xs text-zinc-300 hover:text-white underline cursor-pointer"
            >
                Deselect all
            </button>
        </div>

        <div class="flex items-center gap-2">
            <button 
                type="button" 
                @click="$wire.toggleSelectedStatus(selectedRows); selectedRows = []; selectAll = false" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-800 text-zinc-100 hover:bg-brand hover:text-white border border-zinc-700 transition cursor-pointer"
            >
                <flux:icon name="arrow-path" class="size-3.5" />
                <span>Toggle Status</span>
            </button>

            <button 
                type="button" 
                @click="if(confirm('Are you sure you want to delete ' + selectedRows.length + ' selected role(s)?')) { $wire.deleteSelected(selectedRows); selectedRows = []; selectAll = false }"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-500/20 text-rose-300 hover:bg-rose-600 hover:text-white border border-rose-500/40 transition cursor-pointer"
            >
                <flux:icon name="trash" class="size-3.5" />
                <span>Delete Selected</span>
            </button>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- Search Input -->
        <div class="relative w-full md:w-80">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                <flux:icon name="magnifying-glass" class="size-4" />
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search role name..." 
                class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent transition"
            />
            @if(!empty($search))
                <button 
                    type="button" 
                    wire:click="$set('search', '')" 
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-600"
                >
                    &times;
                </button>
            @endif
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end">
            <!-- Status Filter -->
            <div class="flex items-center gap-1.5 text-xs">
                <label class="text-zinc-500 dark:text-zinc-400 font-medium">Status:</label>
                <select 
                    wire:model.live="statusFilter"
                    class="px-3 py-2 rounded-xl text-xs border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand"
                >
                    <option value="all">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>

            <!-- Per Page -->
            <div class="flex items-center gap-1.5 text-xs">
                <label class="text-zinc-500 dark:text-zinc-400 font-medium">Show:</label>
                <select 
                    wire:model.live="perPage"
                    class="px-3 py-2 rounded-xl text-xs border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand"
                >
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/75 dark:bg-zinc-900/50 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px]">
                        <!-- Bulk Checkbox -->
                        <th class="py-3 px-4 w-10 text-center">
                            <input 
                                type="checkbox" 
                                x-model="selectAll" 
                                @change="toggleSelectAll()" 
                                class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand size-4 cursor-pointer"
                            />
                        </th>

                        <!-- Role Name (Sortable) -->
                        <th class="py-3 px-4 cursor-pointer select-none" wire:click="sortBy('name')">
                            <div class="flex items-center gap-1.5">
                                <span>Role Name</span>
                                @if($sortField === 'name')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3 text-brand" />
                                @endif
                            </div>
                        </th>

                        <!-- Status (Sortable) -->
                        <th class="py-3 px-4 cursor-pointer select-none" wire:click="sortBy('status')">
                            <div class="flex items-center gap-1.5">
                                <span>Status</span>
                                @if($sortField === 'status')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3 text-brand" />
                                @endif
                            </div>
                        </th>

                        <!-- Action -->
                        <th class="py-3 px-4 text-right w-32">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/80 dark:divide-zinc-800/80 text-zinc-700 dark:text-zinc-300">
                    @forelse($roles as $role)
                        @php
                            $roleId = (string) $role->_id;
                            $isActive = (int) ($role->status ?? 0) === 1;
                        @endphp
                        <tr class="odd:bg-zinc-200/70 even:bg-white dark:odd:bg-zinc-950/60 dark:even:bg-[#111c26] hover:bg-zinc-200 dark:hover:bg-zinc-800/80 transition">
                            <!-- Checkbox -->
                            <td class="py-3.5 px-4 text-center">
                                <input 
                                    type="checkbox" 
                                    value="{{ $roleId }}" 
                                    x-model="selectedRows" 
                                    @change="updateHeaderCheckbox()" 
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand size-4 cursor-pointer"
                                />
                            </td>

                            <!-- Role Name -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-8 rounded-lg bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                        <flux:icon name="identification" class="size-4" />
                                    </div>
                                    <div class="font-bold text-sm text-zinc-900 dark:text-white">
                                        {{ $role->name }}
                                    </div>
                                </div>
                            </td>

                            <!-- Status Toggle Badge -->
                            <td class="py-3.5 px-4">
                                <button 
                                    type="button" 
                                    wire:click="toggleStatus('{{ $roleId }}')" 
                                    class="px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer transition {{ $isActive ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20' }}"
                                    title="Click to toggle status"
                                >
                                    <span class="size-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    <span>{{ $isActive ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>

                            <!-- Action: Edit & Delete -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1 justify-end">
                                    <button 
                                        type="button" 
                                        wire:click="toggleStatus('{{ $roleId }}')" 
                                        class="p-2 text-zinc-500 hover:text-brand hover:bg-brand/10 dark:hover:bg-brand/20 rounded-xl transition cursor-pointer"
                                        title="Toggle Status (Currently: {{ $isActive ? 'Active' : 'Inactive' }})"
                                    >
                                        <flux:icon name="arrow-path" class="size-4" />
                                    </button>

                                    <a 
                                        href="{{ route('admin.journal-roles.edit', $roleId) }}" 
                                        wire:navigate 
                                        class="p-2 text-zinc-500 hover:text-brand hover:bg-brand/10 rounded-xl transition cursor-pointer"
                                        title="Edit Role"
                                    >
                                        <flux:icon name="pencil-square" class="size-4" />
                                    </a>

                                    <button 
                                        type="button" 
                                        wire:click="deleteRole('{{ $roleId }}')" 
                                        wire:confirm="Are you sure you want to delete role '{{ addslashes($role->name) }}'?"
                                        class="p-2 text-zinc-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition cursor-pointer"
                                        title="Delete Role"
                                    >
                                        <flux:icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 px-4 text-center">
                                <flux:icon name="briefcase" class="size-8 text-zinc-400 mx-auto mb-2 opacity-50" />
                                <p class="font-bold text-zinc-700 dark:text-zinc-300">No journal roles found.</p>
                                <p class="text-xs text-zinc-400 mt-1">Try refining your search query.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION FOOTER -->
        @if($roles->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $roles->links() }}
            </div>
        @endif
    </div>
</div>
