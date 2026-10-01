<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Manage Users - Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = 'all';

    public string $search = '';
    public string $roleFilter = 'all';
    public int $perPage = 15;
    public ?string $selectedUserId = null;
    public ?User $viewingUser = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
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

    public function with(): array
    {
        $query = User::query();

        if ($this->type !== 'all') {
            if ($this->type === 'publisher') {
                $query->where(function ($q) {
                    $q->whereNotNull('publisher_name')
                      ->where('publisher_name', '!=', '')
                      ->orWhere('role', 'publisher')
                      ->orWhere('user_role', 'publisher');
                });
            } elseif ($this->type === 'python') {
                $query->where(function ($q) {
                    $q->where('type', 'python')
                      ->orWhere('role', 'python')
                      ->orWhere('last_login_with', 'python');
                });
            } elseif ($this->type === 'notified') {
                $query->where(function ($q) {
                    $q->where('notified', 1)
                      ->orWhere('status', 'notified');
                });
            } elseif ($this->type === 'approval') {
                $query->where(function ($q) {
                    $q->where('verification_request_status', 'pending')
                      ->orWhere('verified', 0)
                      ->orWhere('status', 0);
                });
            }
        }

        if (! empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', '%'.$s.'%')
                  ->orWhere('email', 'like', '%'.$s.'%')
                  ->orWhere('first_name', 'like', '%'.$s.'%')
                  ->orWhere('last_name', 'like', '%'.$s.'%')
                  ->orWhere('fullname', 'like', '%'.$s.'%')
                  ->orWhere('role', 'like', '%'.$s.'%');
            });
        }

        if ($this->roleFilter !== 'all') {
            if ($this->roleFilter === 'admin') {
                $query->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhere('is_admin', true)
                      ->orWhere('type', 1);
                });
            } else {
                $query->where('role', $this->roleFilter);
            }
        }

        return [
            'users' => $query->latest()->paginate($this->perPage),
        ];
    }

    public function viewDetails(string $id): void
    {
        $this->selectedUserId = $id;
        $this->viewingUser = User::find($id);
    }

    public function closeModal(): void
    {
        $this->selectedUserId = null;
        $this->viewingUser = null;
    }

    public function toggleAdmin(string $id): void
    {
        $user = User::find($id);
        if ($user) {
            $newAdminState = ! $user->isAdmin();
            $user->is_admin = $newAdminState;
            $user->role = $newAdminState ? 'admin' : 'scholar';
            $user->save();
        }

        if ($this->viewingUser && (string) $this->viewingUser->_id === $id) {
            $this->viewingUser = User::find($id);
        }
    }

    public function deleteUser(string $id): void
    {
        if (auth()->id() === $id) {
            return;
        }

        $user = User::find($id);
        if ($user) {
            $user->delete();
        }

        if ($this->selectedUserId === $id) {
            $this->closeModal();
        }
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-white">
                    @if($type === 'publisher')
                        Publisher Users Management
                    @elseif($type === 'python')
                        Python Users Management
                    @elseif($type === 'notified')
                        Notified Users List
                    @elseif($type === 'approval')
                        Requests For Account Approval
                    @else
                        Users Management
                    @endif
                </h2>
                @if($type !== 'all')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand/10 text-brand border border-brand/20">
                        {{ ucfirst($type) }}
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">View user directories, verify scholar affiliations, and manage administrative privileges.</p>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <!-- Search Input -->
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search scholars by name, email, or role..." 
                class="w-full h-10 pl-10 pr-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
            />
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
            <button 
                type="button" 
                wire:click="$set('roleFilter', 'all')"
                class="px-3 py-2 text-xs font-semibold rounded-xl transition {{ $roleFilter === 'all' ? 'bg-brand text-white shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                All Users
            </button>
            <button 
                type="button" 
                wire:click="$set('roleFilter', 'scholar')"
                class="px-3 py-2 text-xs font-semibold rounded-xl transition {{ $roleFilter === 'scholar' ? 'bg-brand text-white shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                Scholars
            </button>
            <button 
                type="button" 
                wire:click="$set('roleFilter', 'admin')"
                class="px-3 py-2 text-xs font-semibold rounded-xl transition {{ $roleFilter === 'admin' ? 'bg-brand text-white shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                Administrators
            </button>

            <!-- Per Page Selector -->
            <select 
                wire:model.live="perPage" 
                class="h-9 px-2.5 bg-zinc-100 dark:bg-zinc-800 border-none rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-brand/20 cursor-pointer"
            >
                <option value="10">10 / page</option>
                <option value="15">15 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>
        </div>
    </div>

    <!-- USERS TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-900/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">User</th>
                        <th class="px-5 py-3.5">Role</th>
                        <th class="px-5 py-3.5">Affiliation</th>
                        <th class="px-5 py-3.5">Registered</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($users as $user)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                            <!-- User Info -->
                            <td class="px-5 py-4 min-w-[220px]">
                                <div class="flex items-center gap-3">
                                    <div class="size-9 rounded-xl bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ $user->initials() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-zinc-900 dark:text-white leading-snug truncate">
                                            {{ $user->name }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400 truncate">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md {{ $user->isAdmin() ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800' : 'bg-blue-50 dark:bg-blue-950/40 text-brand dark:text-sky-300 border border-brand/20' }}">
                                    {{ $user->isAdmin() ? 'Admin' : ($user->role ?? 'Scholar') }}
                                </span>
                            </td>

                            <!-- Affiliation -->
                            <td class="px-5 py-4 min-w-[160px]">
                                @php
                                    $affiliation = $user->affiliation;
                                    $inst = is_array($affiliation) ? ($affiliation['institution'] ?? '') : (is_string($affiliation) ? $affiliation : '');
                                @endphp
                                <span class="text-zinc-600 dark:text-zinc-300">{{ $inst ?: 'Independent Scholar' }}</span>
                            </td>

                            <!-- Registered Date -->
                            <td class="px-5 py-4 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                {{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-4 whitespace-nowrap text-right space-x-1.5">
                                <button 
                                    type="button" 
                                    wire:click="viewDetails('{{ $user->_id }}')" 
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-brand hover:text-white transition"
                                >
                                    View
                                </button>
                                
                                <button 
                                    type="button" 
                                    wire:click="toggleAdmin('{{ $user->_id }}')" 
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-semibold {{ $user->isAdmin() ? 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200' : 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 hover:bg-purple-600 hover:text-white' }} transition"
                                >
                                    {{ $user->isAdmin() ? 'Revoke Admin' : 'Make Admin' }}
                                </button>

                                @if(auth()->id() !== (string) $user->_id)
                                    <button 
                                        type="button" 
                                        wire:click="deleteUser('{{ $user->_id }}')" 
                                        wire:confirm="Are you sure you want to delete this user?"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-danger hover:bg-danger-light/20 transition"
                                    >
                                        Delete
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-zinc-400">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION BAR -->
        @if($users->hasPages())
            <div class="px-5 py-4 border-t border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- DETAIL MODAL -->
    @if($viewingUser)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-dark-navy/80 backdrop-blur-xs">
            <div class="bg-white dark:bg-[#111c26] rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto border border-zinc-200 dark:border-zinc-800 shadow-2xl p-6 space-y-5">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="size-11 rounded-xl bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-sm">
                            {{ $viewingUser->initials() }}
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                                {{ $viewingUser->name }}
                            </h3>
                            <p class="text-xs text-zinc-500">{{ $viewingUser->email }}</p>
                        </div>
                    </div>
                    <button wire:click="closeModal" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="space-y-4 text-xs text-zinc-600 dark:text-zinc-300">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="font-bold text-zinc-800 dark:text-zinc-200">Role:</span>
                            <p class="capitalize">{{ $viewingUser->role ?? ($viewingUser->isAdmin() ? 'Admin' : 'Scholar') }}</p>
                        </div>
                        <div>
                            <span class="font-bold text-zinc-800 dark:text-zinc-200">Administrator Privileges:</span>
                            <p>{{ $viewingUser->isAdmin() ? 'Yes (Full Access)' : 'No (Standard User)' }}</p>
                        </div>
                    </div>

                    @php
                        $scholarProfile = $viewingUser->scholar_profile;
                    @endphp
                    @if(is_array($scholarProfile))
                        @if(!empty($scholarProfile['bio']))
                            <div>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">Scholar Bio:</span>
                                <p class="leading-relaxed bg-zinc-50 dark:bg-zinc-900/60 p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 mt-1">
                                    {{ $scholarProfile['bio'] }}
                                </p>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">ORCID:</span>
                                <p class="font-mono">{{ $scholarProfile['orcid'] ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">Google Scholar:</span>
                                <p class="font-mono">{{ $scholarProfile['google_scholar'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button wire:click="closeModal" class="px-4 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                        Close
                    </button>
                    <button 
                        wire:click="toggleAdmin('{{ $viewingUser->_id }}')" 
                        class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand text-white hover:bg-brand-600 transition"
                    >
                        {{ $viewingUser->isAdmin() ? 'Revoke Admin Privileges' : 'Grant Admin Privileges' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
