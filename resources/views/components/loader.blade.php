@props([
    'variant' => 'spinner', // 'spinner', 'dots', 'bar', 'overlay', 'full'
    'size' => 'md',         // 'xs', 'sm', 'md', 'lg', 'xl'
    'color' => 'brand',     // 'brand', 'white', 'zinc', 'current'
    'text' => null,
    'wireTarget' => null,   // shortcut for wire:loading wire:target="..."
])

@php
    $sizeClasses = match ($size) {
        'xs' => 'w-3.5 h-3.5',
        'sm' => 'w-4 h-4',
        'lg' => 'w-7 h-7',
        'xl' => 'w-10 h-10',
        default => 'w-5 h-5', // 'md'
    };

    $dotSizes = match ($size) {
        'xs' => 'w-1 h-1',
        'sm' => 'w-1.5 h-1.5',
        'lg' => 'w-2.5 h-2.5',
        'xl' => 'w-3 h-3',
        default => 'w-2 h-2',
    };

    $colorClasses = match ($color) {
        'white' => 'text-white',
        'zinc', 'gray' => 'text-zinc-600 dark:text-zinc-300',
        'current' => 'text-current',
        default => 'text-[#198BEA]', // 'brand'
    };

    $trackColorClasses = match ($color) {
        'white' => 'text-white/20',
        'zinc', 'gray' => 'text-zinc-200 dark:text-zinc-700',
        'current' => 'opacity-25',
        default => 'text-sky-100 dark:text-sky-950/60',
    };

    $textSizeClasses = match ($size) {
        'xs', 'sm' => 'text-xs',
        'lg', 'xl' => 'text-sm font-semibold',
        default => 'text-xs font-medium',
    };
@endphp

@if ($variant === 'overlay')
    {{-- Card / Section Overlay Loader --}}
    <div 
        {{ $attributes->merge(['class' => 'absolute inset-0 z-20 flex flex-col items-center justify-center bg-white/70 dark:bg-zinc-900/70 backdrop-blur-[1px] rounded-2xl transition-all']) }}
        @if($wireTarget) wire:loading wire:target="{{ $wireTarget }}" @endif
    >
        <div class="flex flex-col items-center justify-center gap-2.5 p-4 text-center">
            <svg class="animate-spin {{ $sizeClasses }} {{ $colorClasses }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="{{ $trackColorClasses }}" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
                <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            @if($text)
                <span class="{{ $textSizeClasses }} {{ $colorClasses }} font-medium select-none">{{ $text }}</span>
            @endif
        </div>
    </div>

@elseif ($variant === 'full' || $variant === 'fullscreen')
    {{-- Fullscreen Modal Overlay Loader --}}
    <div 
        {{ $attributes->merge(['class' => 'fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/40 dark:bg-black/60 backdrop-blur-xs transition-opacity']) }}
        @if($wireTarget) wire:loading wire:target="{{ $wireTarget }}" @endif
    >
        <div class="flex flex-col items-center justify-center gap-3 p-6 bg-white dark:bg-zinc-900 rounded-2xl shadow-xl border border-zinc-200 dark:border-zinc-800">
            <svg class="animate-spin {{ $sizeClasses }} {{ $colorClasses }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="{{ $trackColorClasses }}" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
                <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            @if($text)
                <span class="{{ $textSizeClasses }} text-zinc-700 dark:text-zinc-200 font-semibold select-none">{{ $text }}</span>
            @endif
        </div>
    </div>

@elseif ($variant === 'dots')
    {{-- Bouncing Dots Loader --}}
    <div 
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}
        @if($wireTarget) wire:loading wire:target="{{ $wireTarget }}" @endif
    >
        <span class="{{ $dotSizes }} rounded-full bg-current animate-bounce [animation-delay:-0.3s] {{ $colorClasses }}"></span>
        <span class="{{ $dotSizes }} rounded-full bg-current animate-bounce [animation-delay:-0.15s] {{ $colorClasses }}"></span>
        <span class="{{ $dotSizes }} rounded-full bg-current animate-bounce {{ $colorClasses }}"></span>
        @if($text)
            <span class="ml-1.5 {{ $textSizeClasses }} {{ $colorClasses }} select-none">{{ $text }}</span>
        @endif
    </div>

@elseif ($variant === 'bar')
    {{-- Indeterminate Linear Progress Bar --}}
    <div 
        {{ $attributes->merge(['class' => 'w-full h-1 bg-zinc-100 dark:bg-zinc-800 overflow-hidden rounded-full relative']) }}
        @if($wireTarget) wire:loading wire:target="{{ $wireTarget }}" @endif
    >
        <div class="h-full bg-[#198BEA] w-1/3 rounded-full animate-pulse"></div>
    </div>

@else
    {{-- Default Inline / Standalone Spinner --}}
    <div 
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}
        @if($wireTarget) wire:loading wire:target="{{ $wireTarget }}" @endif
    >
        <svg class="animate-spin {{ $sizeClasses }} {{ $colorClasses }} shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="{{ $trackColorClasses }}" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
            <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        @if($text)
            <span class="{{ $textSizeClasses }} {{ $colorClasses }} select-none font-medium">{{ $text }}</span>
        @endif
    </div>
@endif
