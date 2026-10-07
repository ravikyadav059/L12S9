@props([
    'placeholder' => 'Write your content here...',
    'minHeight' => '160px',
    'maxHeight' => '450px',
    'id' => null,
])

@php
    $editorId = $id ?? 'editor-' . Str::random(10);
    $wireModel = $attributes->wire('model')->value();
@endphp

<div class="w-full">
    <textarea 
        id="{{ $editorId }}"
        placeholder="{{ $placeholder }}"
        @if($wireModel) wire:model="{{ $wireModel }}" @endif
        {{ $attributes->whereDoesntStartWith('wire:model')->merge([
            'class' => 'w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 text-zinc-900 dark:text-zinc-100 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all resize-y min-h-[' . $minHeight . ']'
        ]) }}
    ></textarea>
</div>


