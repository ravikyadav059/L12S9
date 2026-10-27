<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Manage Users - Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = 'all';

    #[Url(as: 'search', history: true)]
    public string $search = '';

    #[Url(as: 'role', history: true)]
    public string $roleFilter = 'all';

    public int $perPage = 15;
    public ?string $selectedUserId = null;
    public ?User $viewingUser = null;

    public function mount(): void
    {
        if (request()->has('search')) {
            $this->search = (string) request()->query('search');
            session()->put('admin_users_search', $this->search);
        } elseif (empty($this->search) && session()->has('admin_users_search')) {
            $this->search = (string) session('admin_users_search');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        session()->put('admin_users_search', $this->search);
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
                  ->orWhere('unique_id', 'like', '%'.$s.'%')
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
            } elseif ($this->roleFilter === '0' || $this->roleFilter === 'pending') {
                $query->where(function ($q) {
                    $q->whereIn('status', [0, '0', false, 'pending'])
                      ->orWhereNull('status');
                });
            } elseif ($this->roleFilter === '1' || $this->roleFilter === 'active') {
                $query->whereIn('status', [1, '1', true, 'active']);
            } elseif ($this->roleFilter === '2' || $this->roleFilter === 'redocument') {
                $query->whereIn('status', [2, '2', 'redocument', 're-document']);
            } elseif ($this->roleFilter === '3' || $this->roleFilter === 'reject') {
                $query->whereIn('status', [3, '3', 'reject', 'rejected']);
            } elseif ($this->roleFilter === 'scholar') {
                $query->where('role', 'scholar');
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

    public function updateUserStatus(string $id, int|string $status): void
    {
        $user = User::find($id);
        if ($user) {
            $user->status = (int) $status;
            $user->save();
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

    public function impersonate(string $id): mixed
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if (auth()->id() === $id) {
            return null;
        }

        $targetUser = User::find($id);
        if (! $targetUser) {
            return null;
        }

        $adminId = (string) auth()->id();

        Auth::login($targetUser);
        session()->put('impersonated_by', $adminId);

        return $this->redirect('/', navigate: false);
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

        <!-- Add User Button -->
        <div class="flex items-center gap-2 self-start sm:self-center">
            <button 
                type="button" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] transition shadow-md shadow-brand/20 cursor-pointer"
                title="Add User"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add User</span>
            </button>
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
                class="w-full h-10 pl-10 pr-9 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
            />
            @if (! empty($search))
                <button 
                    type="button" 
                    wire:click="$set('search', '')" 
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 cursor-pointer text-xs"
                    title="Clear search"
                >
                    ✕
                </button>
            @endif
        </div>

        <!-- Filter Dropdown & Per Page -->
        <div class="flex items-center gap-2 flex-wrap">
            <select 
                wire:model.live="roleFilter" 
                class="h-10 px-3 bg-zinc-100 dark:bg-zinc-800 border-none rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-brand/20 cursor-pointer"
            >
                <option value="all">All Users</option>
                <option value="0">Pending (0)</option>
                <option value="1">Active (1)</option>
                <option value="2">Re-Document (2)</option>
                <option value="3">Reject (3)</option>
                <option value="admin">Administrators</option>
                <option value="scholar">Scholars</option>
            </select>

            <!-- Per Page Selector -->
            <select 
                wire:model.live="perPage" 
                class="h-10 px-2.5 bg-zinc-100 dark:bg-zinc-800 border-none rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-brand/20 cursor-pointer"
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
                        <th class="px-5 py-3.5">Profile ID</th>
                        <th class="px-5 py-3.5">First Name</th>
                        <th class="px-5 py-3.5">Last Name</th>
                        <th class="px-5 py-3.5">Role</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($users as $user)
                        <tr class="odd:bg-zinc-200/70 even:bg-white dark:odd:bg-zinc-950/60 dark:even:bg-[#111c26] hover:bg-zinc-200 dark:hover:bg-zinc-800/80 transition">
                            <!-- Profile ID & Email -->
                            <td class="px-5 py-4 min-w-[200px]">
                                <div class="flex items-center gap-3">
                                    <div class="size-9 rounded-xl bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-xs shrink-0 font-mono">
                                        {{ $user->initials() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-mono font-bold text-zinc-900 dark:text-white leading-snug truncate" title="{{ (string) $user->_id }}">
                                            {{ $user->unique_id ?: (string) $user->_id }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400 truncate">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>

                            @php
                                $firstName = trim((string) ($user->first_name ?? ''));
                                $lastName = trim((string) ($user->last_name ?? ''));
                                if ($firstName === '' && $lastName === '') {
                                    $parts = explode(' ', trim((string) ($user->fullname ?: ($user->name ?? ''))), 2);
                                    $firstName = $parts[0] ?? '';
                                    $lastName = $parts[1] ?? '';
                                }
                            @endphp

                            <!-- First Name -->
                            <td class="px-5 py-4 whitespace-nowrap min-w-[120px]">
                                <p class="font-bold text-zinc-900 dark:text-white leading-snug">
                                    {{ $firstName !== '' ? $firstName : '-' }}
                                </p>
                            </td>

                            <!-- Last Name -->
                            <td class="px-5 py-4 whitespace-nowrap min-w-[120px]">
                                <p class="font-bold text-zinc-900 dark:text-white leading-snug">
                                    {{ $lastName !== '' ? $lastName : '-' }}
                                </p>
                            </td>

                            <!-- Role -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md {{ $user->isAdmin() ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800' : 'bg-blue-50 dark:bg-blue-950/40 text-brand dark:text-sky-300 border border-brand/20' }}">
                                    {{ $user->isAdmin() ? 'Admin' : ($user->role ?? 'Scholar') }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                @php
                                    $statusCode = $user->getStatusCode();
                                @endphp
                                @if($statusCode === 1)
                                    <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md inline-flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Active (1)</span>
                                    </span>
                                @elseif($statusCode === 2)
                                    <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md inline-flex items-center gap-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        <span class="size-1.5 rounded-full bg-blue-500"></span>
                                        <span>Re-Document (2)</span>
                                    </span>
                                @elseif($statusCode === 3)
                                    <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md inline-flex items-center gap-1.5 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                                        <span>Reject (3)</span>
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md inline-flex items-center gap-1.5 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        <span>Pending (0)</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-4 whitespace-nowrap text-right space-x-1.5">
                                @if(! empty($user->slug))
                                    <a 
                                        href="{{ route('userscholar', ['slug' => $user->slug]) }}" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-brand hover:text-white transition cursor-pointer"
                                        title="View Profile"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>View</span>
                                    </a>
                                @else
                                    <span 
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-zinc-100/50 dark:bg-zinc-800/40 text-zinc-400 dark:text-zinc-600 cursor-not-allowed"
                                        title="No slug assigned"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>View</span>
                                    </span>
                                @endif

                                @if(! empty($user->unique_id))
                                    <a 
                                        href="{{ route('admin.users.edit', ['user' => $user->unique_id]) }}" 
                                        wire:navigate
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition cursor-pointer"
                                        title="Edit user"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </a>
                                @else
                                    <span 
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-zinc-100/50 dark:bg-zinc-800/40 text-zinc-400 dark:text-zinc-600 cursor-not-allowed"
                                        title="No unique_id assigned"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </span>
                                @endif

                                @if(auth()->id() !== (string) $user->_id)
                                    <button 
                                        type="button" 
                                        wire:click="impersonate('{{ (string) $user->_id }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 hover:bg-amber-500 hover:text-white border border-amber-200 dark:border-amber-800 transition cursor-pointer"
                                        title="Login as this user"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                        </svg>
                                        <span>Login as User</span>
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="deleteUser('{{ (string) $user->_id }}')" 
                                        wire:confirm="Are you sure you want to delete this user?"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-danger hover:bg-danger-light/20 transition cursor-pointer"
                                        title="Delete user"
                                    >
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Delete</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-zinc-400">
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
