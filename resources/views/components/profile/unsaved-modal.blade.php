{{-- resources/views/components/profile/unsaved-modal.blade.php --}}
{{--
  Shared Unsaved Changes Confirmation Modal Component
  ---------------------------------------------------
  Usage across profile & admin edit sections:
    <x-profile.unsaved-modal />
  
  Customized actions / state (optional):
    <x-profile.unsaved-modal 
        show="showDiscardWarning" 
        confirm-action="discardChanges()" 
        cancel-action="stayOnForm()" 
    />
--}}
@props([
    'show' => 'showUnsavedModal',
    'confirmAction' => 'confirmDiscard()',
    'cancelAction' => 'keepEditing()',
    'title' => 'Unsaved Changes',
    'message' => 'You have made changes which are not saved yet. Do you want to discard these changes?',
    'confirmText' => 'Yes, Discard',
    'cancelText' => 'Keep Editing',
])

<div 
    x-show="{{ $show }}" 
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    @keydown.escape.window="{{ $cancelAction }}"
>
    <div 
        @click.outside="{{ $cancelAction }}"
        x-show="{{ $show }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="bg-white dark:bg-[#111c26] rounded-3xl p-8 max-w-[420px] w-full text-center shadow-2xl border border-zinc-100 dark:border-zinc-800"
    >
        <div class="size-16 rounded-full border-2 border-amber-300 bg-amber-50 text-amber-500 flex items-center justify-center text-2xl mx-auto mb-4">
            <i class="fa-solid fa-exclamation"></i>
        </div>

        <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">
            {{ $title }}
        </h3>

        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed mb-6">
            {{ $message }}
        </p>

        <div class="flex items-center justify-center gap-3">
            <button 
                type="button" 
                @click="{{ $confirmAction }}" 
                class="px-6 py-2.5 rounded-full bg-[#ef4444] hover:bg-red-600 text-white text-xs font-bold shadow-md transition-all cursor-pointer active:scale-95"
            >
                {{ $confirmText }}
            </button>

            <button 
                type="button" 
                @click="{{ $cancelAction }}" 
                class="px-6 py-2.5 rounded-full bg-[#505c6e] hover:bg-[#3d4756] text-white text-xs font-bold shadow-md transition-all cursor-pointer active:scale-95"
            >
                {{ $cancelText }}
            </button>
        </div>
    </div>
</div>
