<?php

use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Manage Institutes - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $typeFilter = 'all';
    public string $statusFilter = 'all';
    public int $perPage = 15;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
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

    public function delete(string $id): void
    {
        $organization = Organization::find($id);
        if ($organization) {
            $name = $organization->organization_name;
            $organization->delete();
            session()->flash('message', "Institute '{$name}' was deleted successfully.");
        }
    }

    public function with(): array
    {
        $query = Organization::query();

        // Search by indexed fields: _id, serial_number, slug, and organization_name
        $searchClean = trim($this->search);
        if ($searchClean !== '') {
            $query->where(function ($q) use ($searchClean) {
                // 1. Exact match on _id index (O(1) B-tree lookup)
                if (preg_match('/^[a-f\d]{24}$/i', $searchClean)) {
                    try {
                        $q->orWhere('_id', new \MongoDB\BSON\ObjectId($searchClean))
                            ->orWhere('_id', $searchClean);
                    } catch (\Throwable) {
                        $q->orWhere('_id', $searchClean);
                    }
                }

                // 2. Exact match on serial_number index (O(log N) B-tree lookup)
                if (is_numeric($searchClean)) {
                    $q->orWhere('serial_number', (int) $searchClean)
                        ->orWhere('serial_number', (string) $searchClean);
                }

                // 3. Exact match on slug index (O(log N) lookup)
                $q->orWhere('slug', $searchClean);

                // 4. Regex pattern matching on organization_name and slug
                $regex = new \MongoDB\BSON\Regex(preg_quote($searchClean), 'i');
                $q->orWhere('organization_name', 'regex', $regex)
                    ->orWhere('slug', 'regex', $regex);
            });
        }

        // Type filter (Leverages organization_type_1 index)
        if ($this->typeFilter !== 'all') {
            $query->where('organization_type', $this->typeFilter);
        }

        // Status filter (Leverages status_1 index)
        if ($this->statusFilter === 'active') {
            $query->whereIn('status', [1, '1', true]);
        } elseif ($this->statusFilter === 'inactive') {
            $query->whereIn('status', [0, '0', false]);
        }

        // Sorting (Leverages serial_number_-1, organization_name_1, or created_at_-1 index)
        if ($this->sortField === 'serial_number') {
            $query->orderBy('serial_number', $this->sortDirection);
        } elseif ($this->sortField === 'organization_name') {
            $query->orderBy('organization_name', $this->sortDirection);
        } elseif ($this->sortField === 'created_at') {
            $query->orderBy('created_at', $this->sortDirection);
        } else {
            $query->orderBy('_id', $this->sortDirection);
        }

        $totalCount = (clone $query)->count();
        $items = $query->skip(($this->getPage() - 1) * $this->perPage)
            ->take($this->perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $items,
            $totalCount,
            $this->perPage,
            $this->getPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'institutes' => $paginator,
            'totalCount' => $totalCount,
        ];
    }
};
?>

<div class="space-y-6">
    <!-- TOP HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-brand transition flex items-center gap-1">
                    <flux:icon name="home" class="size-3.5" />
                    <span>Admin</span>
                </a>
                <span>/</span>
                <span class="font-bold text-zinc-900 dark:text-white">Institutes</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                <span>Manage Institutes</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-brand/10 text-brand font-semibold border border-brand/20">
                    {{ number_format($totalCount) }} Total
                </span>
            </h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Search, edit, delete, and organize registered academic universities and corporate organizations.</p>
        </div>

        <div class="flex items-center gap-3">
            <a 
                href="{{ route('institutions.index') }}" 
                target="_blank" 
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition"
            >
                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                <span>Public Portal</span>
            </a>

            <a 
                href="{{ route('admin.institutes.create') }}" 
                wire:navigate 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] transition shadow-md shadow-brand/20 cursor-pointer"
            >
                <flux:icon name="plus" class="size-4" />
                <span>Add Institute</span>
            </a>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    @if(session()->has('message'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300 flex items-center gap-2.5 text-xs font-semibold shadow-xs">
            <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- FILTER BAR -->
    <div class="bg-white dark:bg-[#111c26] p-4 sm:p-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
            <!-- Search -->
            <div class="lg:col-span-6 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                    <flux:icon name="magnifying-glass" class="size-4" />
                </span>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search by institute name, ID, or slug..."
                    class="w-full pl-9 pr-4 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                />
            </div>

            <!-- Type Filter -->
            <div class="lg:col-span-3">
                <select 
                    wire:model.live="typeFilter" 
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                >
                    <option value="all">All Types</option>
                    <option value="education">Education</option>
                    <option value="corporate">Corporate</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="lg:col-span-3">
                <select 
                    wire:model.live="statusFilter" 
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                >
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>
        </div>
    </div>

    <!-- INSTITUTES TABLE CARD -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Institute</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    @forelse($institutes as $org)
                        @php
                            $logoUrl = null;
                            if (!empty($org->logo)) {
                                $logoUrl = str_starts_with($org->logo, 'http') ? $org->logo : asset($org->logo);
                            }
                            $isActive = in_array((string) $org->status, ['1', 1, 'active', true], true);
                        @endphp
                        <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition">
                            <!-- Institute Info -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 flex items-center justify-center overflow-hidden shrink-0">
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $org->organization_name }}" class="size-full object-contain p-0.5" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                                            <div class="hidden size-full items-center justify-center text-zinc-400">
                                                <flux:icon name="academic-cap" class="size-5" />
                                            </div>
                                        @else
                                            <flux:icon name="academic-cap" class="size-5 text-zinc-400" />
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-zinc-900 dark:text-white truncate">
                                            {{ $org->organization_name }}
                                        </p>
                                        <div class="flex items-center gap-2 text-[11px] text-zinc-400 mt-0.5">
                                            @if($org->serial_number)
                                                <span class="font-mono text-zinc-500 dark:text-zinc-400">#{{ $org->serial_number }}</span>
                                            @endif
                                            @if($org->slug)
                                                <span class="truncate max-w-[200px]">{{ $org->slug }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Type -->
                            <td class="py-3 px-4">
                                @if(strtolower((string) $org->organization_type) === 'corporate')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                                        Corporate
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                        Education
                                    </span>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4">
                                @if($isActive)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                        <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <!-- Edit Button -->
                                    <a 
                                        href="{{ route('admin.institutes.edit', (string) $org->_id) }}" 
                                        wire:navigate 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-brand bg-zinc-100 dark:bg-zinc-800 hover:bg-brand/10 dark:hover:bg-brand/20 rounded-lg transition"
                                        title="Edit Institute"
                                    >
                                        <flux:icon name="pencil-square" class="size-3.5" />
                                        <span>Edit</span>
                                    </a>

                                    <!-- Delete Button -->
                                    <button 
                                        type="button"
                                        wire:click="delete('{{ (string) $org->_id }}')"
                                        wire:confirm="Are you sure you want to delete '{{ addslashes($org->organization_name) }}'?"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-white bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-600 dark:hover:bg-rose-600 rounded-lg transition cursor-pointer"
                                        title="Delete Institute"
                                    >
                                        <flux:icon name="trash" class="size-3.5" />
                                        <span>Delete</span>
                                    </button>

                                    <!-- Public Portal Link -->
                                    @if($org->slug)
                                        <a 
                                            href="{{ route('institution.show', $org->slug) }}" 
                                            target="_blank"
                                            class="p-1 text-zinc-400 hover:text-brand hover:bg-brand/10 rounded-lg transition"
                                            title="View Public Profile"
                                        >
                                            <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="max-w-xs mx-auto space-y-3">
                                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                        <flux:icon name="academic-cap" class="size-6" />
                                    </div>
                                    <p class="font-semibold text-zinc-700 dark:text-zinc-300">No institutes found</p>
                                    <p class="text-xs text-zinc-400">Try adjusting your search criteria or register a new institute.</p>
                                    <a 
                                        href="{{ route('admin.institutes.create') }}" 
                                        wire:navigate 
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 transition"
                                    >
                                        <flux:icon name="plus" class="size-3.5" />
                                        <span>Add First Institute</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        @if($institutes->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $institutes->links() }}
            </div>
        @endif
    </div>
</div>
