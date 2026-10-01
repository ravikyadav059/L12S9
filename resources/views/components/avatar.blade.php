@props([
    'user' => null,
    'name' => null,
    'photo' => null,
    'email' => null,
    'size' => 'size-9 text-xs',
    'shape' => 'rounded-full',
    'variant' => 'brand', // 'brand' (brand theme), 'palette' (dynamic color per user), 'neutral'
])

@php
    // 1. Resolve Name, Email, and Initials directly from first_name / last_name
    if ($user) {
        $resolvedName = $user->name;
        $resolvedEmail = (string) ($user->email ?? '');
        $initials = $user->initials();
    } else {
        $resolvedName = trim((string) ($name ?? ''));
        $resolvedEmail = (string) ($email ?? '');
        $source = !empty($resolvedName) ? $resolvedName : (!empty($resolvedEmail) ? Str::before($resolvedEmail, '@') : 'User');
        $cleaned = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $source) ?: 'U');
        $words = array_values(array_filter(preg_split('/\s+/u', $cleaned) ?: []));

        if (count($words) >= 2) {
            $initials = mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1));
        } elseif (!empty($words)) {
            $initials = mb_strtoupper(mb_substr($words[0], 0, min(2, mb_strlen($words[0]))));
        } else {
            $initials = 'U';
        }
    }

    // 3. Resolve Photo URL
    $photoUrl = null;
    $rawPhoto = $photo ?? $user?->photo;

    if (!empty($rawPhoto)) {
        if (Str::startsWith($rawPhoto, ['http://', 'https://', 'data:image/'])) {
            $photoUrl = $rawPhoto;
        } elseif (file_exists(public_path($rawPhoto))) {
            $photoUrl = asset($rawPhoto);
        }
    }

    // 4. Color Palette Variant styling
    $paletteColors = [
        ['bg' => 'bg-sky-500/10 dark:bg-sky-500/20', 'border' => 'border-sky-500/30', 'text' => 'text-sky-600 dark:text-sky-400'],
        ['bg' => 'bg-indigo-500/10 dark:bg-indigo-500/20', 'border' => 'border-indigo-500/30', 'text' => 'text-indigo-600 dark:text-indigo-400'],
        ['bg' => 'bg-emerald-500/10 dark:bg-emerald-500/20', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-600 dark:text-emerald-400'],
        ['bg' => 'bg-violet-500/10 dark:bg-violet-500/20', 'border' => 'border-violet-500/30', 'text' => 'text-violet-600 dark:text-violet-400'],
        ['bg' => 'bg-amber-500/10 dark:bg-amber-500/20', 'border' => 'border-amber-500/30', 'text' => 'text-amber-600 dark:text-amber-400'],
        ['bg' => 'bg-rose-500/10 dark:bg-rose-500/20', 'border' => 'border-rose-500/30', 'text' => 'text-rose-600 dark:text-rose-400'],
        ['bg' => 'bg-teal-500/10 dark:bg-teal-500/20', 'border' => 'border-teal-500/30', 'text' => 'text-teal-600 dark:text-teal-400'],
    ];

    if ($variant === 'palette') {
        $hash = crc32($resolvedEmail ?: $resolvedName ?: 'User');
        $colorScheme = $paletteColors[abs($hash) % count($paletteColors)];
        $fallbackClasses = "{$colorScheme['bg']} border {$colorScheme['border']} {$colorScheme['text']}";
    } elseif ($variant === 'neutral') {
        $fallbackClasses = "bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300";
    } else {
        // Default brand variant matching design system (#198BEA)
        $fallbackClasses = "bg-brand/10 dark:bg-brand/20 border border-brand/25 text-brand dark:text-sky-400";
    }

    $hasPhoto = !empty($photoUrl);
@endphp

<div 
    {{ $attributes->merge(['class' => "relative inline-flex items-center justify-center shrink-0 font-bold select-none overflow-hidden transition-colors {$size} {$shape}"]) }}
    x-data="{ imgLoaded: {{ $hasPhoto ? 'false' : 'true' }}, imgFailed: false }"
>
    @if($hasPhoto)
        <img 
            src="{{ $photoUrl }}" 
            alt="{{ $resolvedName ?: 'User Avatar' }}" 
            class="w-full h-full object-cover {{ $shape }} transition-opacity duration-200"
            :class="imgLoaded && !imgFailed ? 'opacity-100' : 'opacity-0 absolute inset-0'"
            x-on:load="imgLoaded = true"
            x-on:error="imgFailed = true"
            loading="lazy"
        />
    @endif

    {{-- Fallback rendered if no photo is configured, or if image URL 404s/fails to load --}}
    <div 
        x-show="!{{ $hasPhoto ? 'true' : 'false' }} || imgFailed || !imgLoaded"
        class="w-full h-full flex items-center justify-center text-center tracking-tight {{ $shape }} {{ $fallbackClasses }}"
        aria-hidden="true"
    >
        <span>{{ $initials }}</span>
    </div>
</div>
