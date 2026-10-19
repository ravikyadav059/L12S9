<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Laravel' }}</title>

<!-- FAVICON -->
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700|dm-sans:400,500,600,700" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<!-- Quill Editor v1.3.6 -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.6/dist/quill.min.js"></script>
<style>
    /* Quill Editor Auto-scroll & Min/Max Height */
    .quill-editor-wrapper .ql-container {
        font-family: inherit;
        font-size: 0.875rem;
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
</style>


