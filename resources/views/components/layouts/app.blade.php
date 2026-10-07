<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen flex flex-col bg-[#f9fafb] dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 antialiased">
    
    <!-- 1. Header (Common across all pages) -->
    <x-layouts.app.header />

    <!-- 2. Main Page Content (Injected dynamically) -->
    <main class="flex-1 w-full">
        {{ $slot }}
    </main>

    <!-- 3. Footer (Common across all pages) -->
    <x-layouts.app.footer />

    <!-- 4. Global Modals & Notifications -->
    <x-modals.share />
    <x-toast />

    @fluxScripts
</body>
</html>
