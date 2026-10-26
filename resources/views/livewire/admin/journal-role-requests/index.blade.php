<?php

use App\Models\JournalRole;
use App\Models\ReviewerJournal;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('All Journal Role Requests - Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $statusFilter = 'all';

    public string $roleFilter = 'all';

    public int $perPage = 15;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
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
        $request = RoleInResearchJournals::find($id);
        if ($request) {
            $currentStatus = (int) ($request->status ?? 0);
            $newStatus = $currentStatus === 1 ? 0 : 1;
            $request->status = $newStatus;
            $request->save();

            $statusLabel = $newStatus === 1 ? 'Approved / Active' : 'Pending / Inactive';
            session()->flash('message', "Status updated to '{$statusLabel}'.");
        }
    }

    public function deleteRequest(string $id): void
    {
        $request = RoleInResearchJournals::find($id);
        if ($request) {
            $request->delete();
            session()->flash('message', 'Journal role request deleted successfully.');
        }
    }

    public function deleteSelected(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        RoleInResearchJournals::whereIn('_id', $ids)->delete();
        session()->flash('message', count($ids).' role request(s) deleted successfully.');
    }

    public function toggleSelectedStatus(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        $requests = RoleInResearchJournals::whereIn('_id', $ids)->get();
        foreach ($requests as $req) {
            $currentStatus = (int) ($req->status ?? 0);
            $req->status = $currentStatus === 1 ? 0 : 1;
            $req->save();
        }

        session()->flash('message', 'Status toggled for '.count($ids).' request(s).');
    }

    public function with(): array
    {
        $query = RoleInResearchJournals::query()->with(['reviewerJournal', 'user', 'journalRole']);

        // Search logic
        $searchClean = trim($this->search);
        if ($searchClean !== '') {
            $regex = new \MongoDB\BSON\Regex(preg_quote($searchClean, '/'), 'i');

            // Find matching user IDs
            $matchedUserIds = User::where('name', 'regex', $regex)
                ->orWhere('email', 'regex', $regex)
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            // Find matching journal IDs
            $matchedJournalIds = ReviewerJournal::where('journal_title', 'regex', $regex)
                ->orWhere('journal_short_name', 'regex', $regex)
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            // Find matching role IDs
            $matchedRoleIds = JournalRole::where('name', 'regex', $regex)
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $query->where(function ($q) use ($searchClean, $regex, $matchedUserIds, $matchedJournalIds, $matchedRoleIds) {
                if (preg_match('/^[a-f\d]{24}$/i', $searchClean)) {
                    try {
                        $q->orWhere('_id', new \MongoDB\BSON\ObjectId($searchClean))
                            ->orWhere('_id', $searchClean);
                    } catch (\Throwable) {
                        $q->orWhere('_id', $searchClean);
                    }
                }

                if (! empty($matchedUserIds)) {
                    $q->orWhereIn('user_id', $matchedUserIds);
                }

                if (! empty($matchedJournalIds)) {
                    $q->orWhereIn('journal_title', $matchedJournalIds);
                }

                if (! empty($matchedRoleIds)) {
                    $q->orWhereIn('role', $matchedRoleIds);
                }

                // Also match raw legacy strings stored in journal_title or role
                $q->orWhere('journal_title', 'regex', $regex)
                    ->orWhere('journal_short_name', 'regex', $regex)
                    ->orWhere('role', 'regex', $regex);
            });
        }

        // Status filter
        if ($this->statusFilter === 'active') {
            $query->whereIn('status', [1, '1', true]);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where(function ($q) {
                $q->whereIn('status', [0, '0', false])
                    ->orWhereNull('status');
            });
        }

        // Role filter
        if ($this->roleFilter !== 'all') {
            $query->where(function ($q) {
                $q->where('role', $this->roleFilter);
            });
        }

        // Sorting
        if ($this->sortField === 'status') {
            $query->orderBy('status', $this->sortDirection);
        } else {
            $query->orderBy('created_at', $this->sortDirection);
        }

        /** @var LengthAwarePaginator $roleRequests */
        $roleRequests = $query->paginate($this->perPage);

        return [
            'roleRequests' => $roleRequests,
            'availableRoles' => JournalRole::getCachedActive(),
            'currentPageIds' => $roleRequests->pluck('_id')->map(fn ($id) => (string) $id)->values()->toArray(),
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
                <span class="font-bold text-zinc-900 dark:text-white">All Journal Role Requests</span>
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="user-group" class="size-6 text-brand" />
                <span>All Journal Role Requests</span>
            </h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                Review and moderate scholar role applications, editorial assignments, and reviewer requests.
            </p>
        </div>

        <div>
            <span class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                Total Requests: <strong>{{ $roleRequests->total() }}</strong>
            </span>
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
                @click="if(confirm('Are you sure you want to delete ' + selectedRows.length + ' selected role request(s)?')) { $wire.deleteSelected(selectedRows); selectedRows = []; selectAll = false }"
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
                placeholder="Search journal, user, role name..." 
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

        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto justify-between md:justify-end">
            <!-- Role Filter -->
            <div class="flex items-center gap-1.5 text-xs">
                <label class="text-zinc-500 dark:text-zinc-400 font-medium">Role:</label>
                <select 
                    wire:model.live="roleFilter"
                    class="px-3 py-2 rounded-xl text-xs border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand"
                >
                    <option value="all">All Roles</option>
                    @foreach($availableRoles as $r)
                        <option value="{{ $r['id'] ?? $r['name'] }}">{{ $r['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div class="flex items-center gap-1.5 text-xs">
                <label class="text-zinc-500 dark:text-zinc-400 font-medium">Status:</label>
                <select 
                    wire:model.live="statusFilter"
                    class="px-3 py-2 rounded-xl text-xs border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand"
                >
                    <option value="all">All Status</option>
                    <option value="active">Active / Approved</option>
                    <option value="inactive">Pending / Inactive</option>
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

                        <!-- Journal Title -->
                        <th class="py-3 px-4">Journal Title</th>

                        <!-- User Name -->
                        <th class="py-3 px-4">User Name</th>

                        <!-- Role Name -->
                        <th class="py-3 px-4">Role Name</th>

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
                        <th class="py-3 px-4 text-right w-28">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/80 dark:divide-zinc-800/80 text-zinc-700 dark:text-zinc-300">
                    @forelse($roleRequests as $item)
                        @php
                            $requestId = (string) $item->_id;
                            $isActive = (int) ($item->status ?? 0) === 1;
                            $journal = $item->reviewerJournal;
                            $user = $item->user;
                            $journalTitle = $journal?->journal_title ?? ($item->journal_title ?: '—');
                            $shortName = $journal?->journal_short_name ?? ($item->journal_short_name ?: null);
                            $userName = $user?->name ?? '—';
                            $userEmail = $user?->email ?? null;
                            $roleName = $item->journalRole?->name ?? ($item->role ?: '—');
                        @endphp
                        <tr class="odd:bg-zinc-200/70 even:bg-white dark:odd:bg-zinc-950/60 dark:even:bg-[#111c26] hover:bg-zinc-200 dark:hover:bg-zinc-800/80 transition">
                            <!-- Checkbox -->
                            <td class="py-3.5 px-4 text-center">
                                <input 
                                    type="checkbox" 
                                    value="{{ $requestId }}" 
                                    x-model="selectedRows" 
                                    @change="updateHeaderCheckbox()" 
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand size-4 cursor-pointer"
                                />
                            </td>

                            <!-- Journal Title -->
                            <td class="py-3.5 px-4 max-w-sm">
                                <div class="font-bold text-sm text-zinc-900 dark:text-white leading-snug line-clamp-2" title="{{ $journalTitle }}">
                                    {{ $journalTitle }}
                                </div>
                            </td>

                            <!-- User Name -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-8 rounded-full bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ $user ? $user->initials() : '?' }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-zinc-900 dark:text-white leading-snug truncate">
                                            {{ $userName }}
                                        </p>
                                        @if($userEmail)
                                            <p class="text-[11px] text-zinc-400 truncate">{{ $userEmail }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Role Name -->
                            <td class="py-3.5 px-4">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20">
                                    <flux:icon name="identification" class="size-3.5" />
                                    <span>{{ $roleName }}</span>
                                </div>
                                @if(!empty($item->start_month_year))
                                    <div class="text-[11px] text-zinc-400 mt-1">
                                        {{ $item->start_month_year }} &rarr; {{ (int)$item->not_ended_yet === 1 ? 'Present' : ($item->end_month_year ?: 'Present') }}
                                    </div>
                                @endif
                            </td>

                            <!-- Status Toggle -->
                            <td class="py-3.5 px-4">
                                <button 
                                    type="button" 
                                    wire:click="toggleStatus('{{ $requestId }}')" 
                                    class="px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1.5 cursor-pointer transition {{ $isActive ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20' }}"
                                    title="Click to toggle status"
                                >
                                    <span class="size-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    <span>{{ $isActive ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </td>

                            <!-- Action -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1 justify-end">
                                    <button 
                                        type="button" 
                                        wire:click="toggleStatus('{{ $requestId }}')" 
                                        class="p-2 text-zinc-500 hover:text-brand hover:bg-brand/10 dark:hover:bg-brand/20 rounded-xl transition cursor-pointer"
                                        title="Toggle Status (Currently: {{ $isActive ? 'Active' : 'Inactive' }})"
                                    >
                                        <flux:icon name="arrow-path" class="size-4" />
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="deleteRequest('{{ $requestId }}')" 
                                        wire:confirm="Are you sure you want to delete this role request?"
                                        class="p-2 text-zinc-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition cursor-pointer"
                                        title="Delete Role Request"
                                    >
                                        <flux:icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 px-4 text-center">
                                <flux:icon name="user-group" class="size-8 text-zinc-400 mx-auto mb-2 opacity-50" />
                                <p class="font-bold text-zinc-700 dark:text-zinc-300">No journal role requests found.</p>
                                <p class="text-xs text-zinc-400 mt-1">Try refining your search query or filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION FOOTER -->
        @if($roleRequests->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $roleRequests->links() }}
            </div>
        @endif
    </div>
</div>
