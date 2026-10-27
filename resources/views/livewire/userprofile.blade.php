<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';
    public ?User $user = null;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->user = User::where('slug', $slug)->first();
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-zinc-900 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($user)
            <div class="bg-white dark:bg-[#111c26] rounded-2xl p-6 sm:p-8 border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-4">
                <div class="flex items-center gap-4">
                    <div class="size-16 rounded-2xl bg-brand/10 border border-brand/20 text-brand font-bold flex items-center justify-center text-xl shrink-0">
                        {{ $user->initials() }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                            {{ $user->fullname ?: ($user->name ?: 'Scholar Profile') }}
                        </h1>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                        @if(!empty($user->role))
                            <span class="inline-block mt-2 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 capitalize">
                                {{ $user->role }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-[#111c26] rounded-2xl p-8 text-center border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <p class="text-zinc-500">Scholar profile not found.</p>
            </div>
        @endif
    </div>
</div>
