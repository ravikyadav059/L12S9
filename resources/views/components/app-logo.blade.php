@props(['dark' => false])

<img {{ $attributes->merge(['class' => 'h-8 w-auto']) }} src="{{ $dark ? asset('images/sitelogo/logo-dark-mode.webp') : asset('images/sitelogo/logo.webp') }}" alt="Scholar9 Logo" />
