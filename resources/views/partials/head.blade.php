<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Laravel' }}</title>

<!-- FAST THEME INITIALIZATION TO PREVENT FLASH OF DARK MODE / UNSTYLED THEME (FOUC) -->
<script>
    (function () {
        function syncTheme() {
            try {
                const appearance = localStorage.getItem('flux.appearance') || 'system';
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        }
        syncTheme();
        document.addEventListener('livewire:navigated', syncTheme);
    })();
</script>

<!-- FAVICON -->
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700|dm-sans:400,500,600,700|space-grotesk:400,500,600,700" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Quill Editor v1.3.6 -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.min.js"></script>

<!-- International Telephone Input v18.1.1 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/css/intlTelInput.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/intlTelInput.min.js"></script>

<style>
    /* Prevent Alpine Flash of Unstyled Content (FOUC) */
    [x-cloak] {
        display: none !important;
    }

    /* Quill Editor Auto-scroll & Min/Max Height */
    .quill-editor-wrapper .ql-container {
        font-family: inherit;
        font-size: 0.875rem;
        border: none !important;
        overflow: visible !important;
        max-height: none !important;
    }
    .quill-editor-wrapper .ql-editor {
        min-height: 160px;
        max-height: 280px;
        overflow-y: auto;
        font-family: inherit;
        font-size: 0.875rem;
        line-height: 1.6;
    }
    .quill-editor-wrapper .ql-editor::-webkit-scrollbar {
        width: 6px;
    }
    .quill-editor-wrapper .ql-editor::-webkit-scrollbar-track {
        background: transparent;
    }
    .quill-editor-wrapper .ql-editor::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 3px;
    }
    .dark .quill-editor-wrapper .ql-editor::-webkit-scrollbar-thumb {
        background-color: rgba(113, 113, 122, 0.5);
    }

    /* International Telephone Input (intl-tel-input) Custom Styles */
    .iti {
        width: 100% !important;
        display: block !important;
    }
    .iti input.iti__tel-input {
        width: 100% !important;
    }
    .iti__country-list {
        z-index: 100 !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
        border: 1px solid #e4e4e7 !important;
        font-family: inherit !important;
        font-size: 0.8125rem !important;
        max-height: 240px !important;
    }
    .dark .iti__country-list {
        background-color: #111c26 !important;
        border-color: #27272a !important;
        color: #f4f4f5 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6) !important;
    }
    .dark .iti__country.iti__highlight {
        background-color: #1e293b !important;
    }
    .dark .iti__country-name, .dark .iti__dial-code {
        color: inherit !important;
    }
    .dark .iti__selected-dial-code {
        color: #f4f4f5 !important;
    }
    .dark .iti__arrow {
        border-top-color: #a1a1aa !important;
    }
    .dark .iti__arrow--up {
        border-bottom-color: #a1a1aa !important;
    }
    .iti__selected-flag {
        border-top-left-radius: 0.75rem !important;
        border-bottom-left-radius: 0.75rem !important;
        padding-left: 12px !important;
    }
    .dark .iti__selected-flag:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
</style>

<!-- Client Timezone Auto-detection Cookie -->
<script>
    (function() {
        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (tz && !document.cookie.includes('app_timezone=' + encodeURIComponent(tz))) {
                const exp = new Date(Date.now() + 365 * 864e5).toUTCString();
                document.cookie = 'app_timezone=' + encodeURIComponent(tz) + ';expires=' + exp + ';path=/;SameSite=Lax';
            }
        } catch (e) {}
    })();
</script>
