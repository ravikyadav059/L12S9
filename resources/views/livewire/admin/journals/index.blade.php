<?php

use App\Models\ReviewerJournal;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Manage Journals - Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    public string $statusFilter = 'all';
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
        $journal = ReviewerJournal::find($id);
        if ($journal) {
            $title = $journal->journal_title ?: 'Untitled Journal';
            $journal->delete();
            session()->flash('message', "Journal '{$title}' was deleted successfully.");
        }
    }

    public function with(): array
    {
        $query = ReviewerJournal::query();

        // Indexed search: _id, journal_title, journal_short_name, slug, e_issn, p_issn, issn
        $searchClean = trim($this->search);
        if ($searchClean !== '') {
            $query->where(function ($q) use ($searchClean) {
                if (preg_match('/^[a-f\d]{24}$/i', $searchClean)) {
                    try {
                        $q->orWhere('_id', new \MongoDB\BSON\ObjectId($searchClean))
                            ->orWhere('_id', $searchClean);
                    } catch (\Throwable) {
                        $q->orWhere('_id', $searchClean);
                    }
                }

                $q->orWhere('slug', $searchClean)
                    ->orWhere('e_issn', $searchClean)
                    ->orWhere('p_issn', $searchClean)
                    ->orWhere('issn', $searchClean);

                $regex = new \MongoDB\BSON\Regex(preg_quote($searchClean), 'i');
                $q->orWhere('journal_title', 'regex', $regex)
                    ->orWhere('journal_short_name', 'regex', $regex)
                    ->orWhere('slug', 'regex', $regex);
            });
        }

        // Status filter
        if ($this->statusFilter === 'active') {
            $query->whereIn('status', [1, '1', true]);
        } elseif ($this->statusFilter === 'inactive') {
            $query->whereIn('status', [0, '0', false]);
        }

        // Sorting
        if ($this->sortField === 'journal_title') {
            $query->orderBy('journal_title', $this->sortDirection);
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
            'journals' => $paginator,
            'totalCount' => $totalCount,
        ];
    }
};
?>

<div class="space-y-6 w-full">
    <!-- TOP HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-brand transition flex items-center gap-1">
                    <flux:icon name="home" class="size-3.5" />
                    <span>Admin</span>
                </a>
                <span>/</span>
                <span class="font-bold text-zinc-900 dark:text-white">Journals</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                <span>Manage Journals</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-brand/10 text-brand font-semibold border border-brand/20">
                    {{ number_format($totalCount) }} Total
                </span>
            </h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Review, register, edit, and organize peer-reviewed scholarly journals and publisher associations.</p>
        </div>

        <div class="flex items-center gap-3">
            <a 
                href="{{ route('journals.index') }}" 
                target="_blank" 
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition"
            >
                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                <span>Public Portal</span>
            </a>

            <a 
                href="{{ route('admin.journals.create') }}" 
                wire:navigate 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] transition shadow-md shadow-brand/20 cursor-pointer"
            >
                <flux:icon name="plus" class="size-4" />
                <span>Add Journal</span>
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
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <!-- Search -->
            <div class="sm:col-span-8 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                    <flux:icon name="magnifying-glass" class="size-4" />
                </span>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search by journal full name, short name, ISSN, slug, or ID..."
                    class="w-full pl-9 pr-4 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                />
            </div>

            <!-- Status Filter -->
            <div class="sm:col-span-4">
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

    <!-- TABLE CARD -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Journal</th>
                        <th class="py-3 px-4">Short Form</th>
                        <th class="py-3 px-4">ISSN No.</th>
                        <th class="py-3 px-4">Publisher</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    @forelse($journals as $j)
                        @php
                            $photoUrl = null;
                            if (!empty($j->journal_photo)) {
                                $photoUrl = str_starts_with($j->journal_photo, 'http') ? $j->journal_photo : asset($j->journal_photo);
                            }
                            $isActive = in_array((string) $j->status, ['1', 1, 'active', true], true);
                            $title = $j->journal_title;
                        @endphp
                        <tr class="odd:bg-zinc-200/70 even:bg-white dark:odd:bg-zinc-950/60 dark:even:bg-[#111c26] hover:bg-zinc-200 dark:hover:bg-zinc-800/80 transition">
                            <!-- Photo & Title (Compact size, full title only, no slug) -->
                            <td class="py-2.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-9 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 flex items-center justify-center overflow-hidden shrink-0">
                                        @if($photoUrl)
                                            <img src="{{ $photoUrl }}" alt="{{ $title }}" class="size-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                                            <div class="hidden size-full items-center justify-center text-zinc-400">
                                                <flux:icon name="book-open" class="size-4" />
                                            </div>
                                        @else
                                            <flux:icon name="book-open" class="size-4 text-zinc-400" />
                                        @endif
                                    </div>
                                    <div class="min-w-0 max-w-xs sm:max-w-sm">
                                        <p class="font-semibold text-xs text-zinc-900 dark:text-white leading-snug line-clamp-2" title="{{ $title }}">
                                            {{ $title }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Short Form (Only Short Name, no Est, no full publisher name) -->
                            <td class="py-3 px-4">
                                <span class="font-semibold text-brand text-xs">
                                    {{ $j->journal_short_name ?: '-' }}
                                </span>
                            </td>

                            <!-- ISSN -->
                            <td class="py-3 px-4 font-mono text-xs">
                                <div class="space-y-1">
                                    @if($j->e_issn)
                                        <div class="text-zinc-800 dark:text-zinc-200 font-medium">
                                            <span class="text-brand dark:text-brand font-bold">e:</span> {{ $j->e_issn }}
                                        </div>
                                    @endif
                                    @if($j->p_issn)
                                        <div class="text-zinc-800 dark:text-zinc-200 font-medium">
                                            <span class="text-brand dark:text-brand font-bold">p:</span> {{ $j->p_issn }}
                                        </div>
                                    @endif
                                    @if(!$j->e_issn && !$j->p_issn)
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Publisher -->
                            <td class="py-3 px-4">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300 break-words">
                                    {{ $j->organization_name ?: '-' }}
                                </span>
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
                                    <a 
                                        href="{{ route('admin.journals.edit', (string) $j->_id) }}" 
                                        wire:navigate 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-brand bg-zinc-100 dark:bg-zinc-800 hover:bg-brand/10 dark:hover:bg-brand/20 rounded-lg transition"
                                        title="Edit Journal"
                                    >
                                        <flux:icon name="pencil-square" class="size-3.5" />
                                        <span>Edit</span>
                                    </a>

                                    <button 
                                        type="button"
                                        wire:click="delete('{{ (string) $j->_id }}')"
                                        wire:confirm="Are you sure you want to delete '{{ addslashes($title) }}'?"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-white bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-600 dark:hover:bg-rose-600 rounded-lg transition cursor-pointer"
                                        title="Delete Journal"
                                    >
                                        <flux:icon name="trash" class="size-3.5" />
                                        <span>Delete</span>
                                    </button>

                                    @if($j->slug)
                                        <a 
                                            href="{{ route('journal.show', $j->slug) }}" 
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
                            <td colspan="6" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="max-w-xs mx-auto space-y-3">
                                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                        <flux:icon name="book-open" class="size-6" />
                                    </div>
                                    <p class="font-semibold text-zinc-700 dark:text-zinc-300">No journals found</p>
                                    <p class="text-xs text-zinc-400">Try adjusting your search query or register a new journal.</p>
                                    <a 
                                        href="{{ route('admin.journals.create') }}" 
                                        wire:navigate 
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 transition"
                                    >
                                        <flux:icon name="plus" class="size-3.5" />
                                        <span>Add First Journal</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($journals->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $journals->links() }}
            </div>
        @endif
    </div>
</div>
