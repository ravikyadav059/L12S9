@props([
    'placeholder' => 'Write your content here...',
    'minHeight' => '160px',
    'maxHeight' => '450px',
    'id' => null,
])

@php
    $editorId = $id ?? 'quill-editor-' . Str::random(10);
    $wireModel = $attributes->wire('model')->value();
@endphp

<div 
    wire:ignore
    x-data="quillEditorComponent({
        content: @entangle($wireModel),
        placeholder: @js($placeholder),
        minHeight: @js($minHeight),
        maxHeight: @js($maxHeight)
    })"
    class="quill-wrapper border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white dark:bg-zinc-800/80 focus-within:border-[#198BEA] focus-within:ring-2 focus-within:ring-[#198BEA]/15 transition-all"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <div x-ref="editorContainer" id="{{ $editorId }}" style="min-height: {{ $minHeight }}; max-height: {{ $maxHeight }};"></div>
</div>

<script>
    (function() {
        function registerQuillComponent() {
            if (window.Alpine && !window.__quillComponentRegistered) {
                window.__quillComponentRegistered = true;
                
                Alpine.data('quillEditorComponent', function(config) {
                    return {
                        editor: null,
                        content: config.content,
                        placeholder: config.placeholder || 'Write your content here...',
                        isUpdatingFromQuill: false,

                        loadQuill() {
                            if (window.Quill) {
                                return Promise.resolve(window.Quill);
                            }
                            if (window.__quillLoadingPromise) {
                                return window.__quillLoadingPromise;
                            }
                            
                            window.__quillLoadingPromise = new Promise(function(resolve, reject) {
                                if (!document.getElementById('quill-snow-css')) {
                                    const link = document.createElement('link');
                                    link.id = 'quill-snow-css';
                                    link.rel = 'stylesheet';
                                    link.href = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css';
                                    document.head.appendChild(link);
                                }
                                
                                const script = document.createElement('script');
                                script.src = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js';
                                script.async = true;
                                script.onload = function() { resolve(window.Quill); };
                                script.onerror = reject;
                                document.head.appendChild(script);
                            });
                            
                            return window.__quillLoadingPromise;
                        },

                        init() {
                            const self = this;
                            this.loadQuill().then(function(Quill) {
                                const container = self.$refs.editorContainer;
                                if (!container) return;

                                // Prevent duplicate Quill initialization on the same container
                                if (self.editor && container.querySelector('.ql-editor')) return;

                                // Purge any existing leftover toolbar siblings from previous renders/page snapshots
                                const wrapper = container.closest('.quill-wrapper') || container.parentElement;
                                if (wrapper) {
                                    const toolbars = wrapper.querySelectorAll('.ql-toolbar');
                                    toolbars.forEach(function(tb) {
                                        tb.remove();
                                    });
                                }

                                // Reset any previously attached Quill classes/elements on the container
                                container.classList.remove('ql-container', 'ql-snow');
                                const existingEditor = container.querySelector('.ql-editor');
                                const initialText = existingEditor ? existingEditor.innerHTML : (self.content || '');
                                container.innerHTML = '';

                                const toolbarOptions = [
                                    [{ 'header': [2, 3, false] }],
                                    ['bold', 'italic', 'underline', 'strike'],
                                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                                    ['blockquote', 'code-block'],
                                    ['link', 'image'],
                                    ['clean']
                                ];

                                self.editor = new Quill(container, {
                                    theme: 'snow',
                                    placeholder: self.placeholder,
                                    modules: {
                                        toolbar: toolbarOptions
                                    }
                                });

                                // Apply tooltips to all Quill toolbar buttons & pickers
                                const toolbar = self.editor.getModule('toolbar') ? self.editor.getModule('toolbar').container : null;
                                if (toolbar) {
                                    const tooltips = {
                                        'button.ql-bold': 'Bold (Ctrl+B)',
                                        'button.ql-italic': 'Italic (Ctrl+I)',
                                        'button.ql-underline': 'Underline (Ctrl+U)',
                                        'button.ql-strike': 'Strikethrough',
                                        'button.ql-blockquote': 'Blockquote / Quote',
                                        'button.ql-code-block': 'Code Block',
                                        'button.ql-link': 'Insert Link',
                                        'button.ql-image': 'Insert Image',
                                        'button.ql-clean': 'Clear Formatting',
                                        'button.ql-list[value="ordered"]': 'Numbered List',
                                        'button.ql-list[value="bullet"]': 'Bulleted List',
                                        '.ql-picker.ql-header': 'Heading Style / Text Size',
                                        '.ql-picker.ql-header .ql-picker-label': 'Heading Style / Text Size',
                                        '.ql-picker-item[data-value="2"]': 'Heading 2',
                                        '.ql-picker-item[data-value="3"]': 'Heading 3',
                                        '.ql-picker-item:not([data-value])': 'Normal Text'
                                    };

                                    for (const selector in tooltips) {
                                        const title = tooltips[selector];
                                        const items = toolbar.querySelectorAll(selector);
                                        for (let i = 0; i < items.length; i++) {
                                            items[i].setAttribute('title', title);
                                            items[i].setAttribute('aria-label', title);
                                        }
                                    }
                                }

                                // Set initial content
                                if (self.content) {
                                    self.editor.root.innerHTML = self.content;
                                }

                                // When user types in Quill -> update Livewire
                                self.editor.on('text-change', function() {
                                    self.isUpdatingFromQuill = true;
                                    const html = self.editor.root.innerHTML;
                                    self.content = (html === '<p><br></p>' || html.trim() === '') ? '' : html;
                                    self.$nextTick(function() {
                                        self.isUpdatingFromQuill = false;
                                    });
                                });

                                // When Livewire model changes from outside -> update Quill
                                self.$watch('content', function(newVal) {
                                    if (self.isUpdatingFromQuill) return;
                                    const currentHtml = self.editor.root.innerHTML;
                                    const normalizedNew = newVal || '';
                                    if (normalizedNew !== currentHtml && (normalizedNew !== '' || currentHtml !== '<p><br></p>')) {
                                        self.editor.root.innerHTML = normalizedNew;
                                    }
                                });
                            });
                        }
                    };
                });
            }
        }

        if (window.Alpine) {
            registerQuillComponent();
        } else {
            document.addEventListener('alpine:init', registerQuillComponent);
        }
    })();
</script>

<style>
    /* Custom Quill styling matching Scholar9 theme */
    .quill-wrapper .ql-toolbar.ql-snow {
        border: none !important;
        border-bottom: 1px solid rgba(228, 228, 231, 0.8) !important;
        background-color: #f4f4f5;
        padding: 8px 12px;
        font-family: inherit;
    }
    .dark .quill-wrapper .ql-toolbar.ql-snow {
        border-bottom: 1px solid rgba(63, 63, 70, 0.8) !important;
        background-color: #27272a;
    }
    .quill-wrapper .ql-container.ql-snow {
        border: none !important;
        font-family: inherit;
        font-size: 0.875rem;
    }
    .quill-wrapper .ql-editor {
        min-height: {{ $minHeight }};
        padding: 12px 16px;
        line-height: 1.6;
        color: #18181b;
    }
    .dark .quill-wrapper .ql-editor {
        color: #f4f4f5;
    }
    .quill-wrapper .ql-editor.ql-blank::before {
        color: #a1a1aa;
        font-style: normal;
        left: 16px;
        right: 16px;
    }
    .dark .quill-wrapper .ql-snow .ql-stroke {
        stroke: #d4d4d8;
    }
    .dark .quill-wrapper .ql-snow .ql-fill {
        fill: #d4d4d8;
    }
    .dark .quill-wrapper .ql-snow .ql-picker {
        color: #d4d4d8;
    }
    .dark .quill-wrapper .ql-snow .ql-picker-options {
        background-color: #18181b;
        border-color: #3f3f46;
    }
    .quill-wrapper .ql-snow .ql-picker.ql-expanded .ql-picker-label {
        color: #198BEA;
    }
    .quill-wrapper .ql-snow.ql-toolbar button:hover,
    .quill-wrapper .ql-snow .ql-toolbar button:hover,
    .quill-wrapper .ql-snow.ql-toolbar button.ql-active,
    .quill-wrapper .ql-snow .ql-toolbar button.ql-active {
        color: #198BEA;
    }
    .quill-wrapper .ql-snow.ql-toolbar button:hover .ql-stroke,
    .quill-wrapper .ql-snow .ql-toolbar button:hover .ql-stroke,
    .quill-wrapper .ql-snow.ql-toolbar button.ql-active .ql-stroke,
    .quill-wrapper .ql-snow .ql-toolbar button.ql-active .ql-stroke {
        stroke: #198BEA !important;
    }
    .quill-wrapper .ql-snow.ql-toolbar button:hover .ql-fill,
    .quill-wrapper .ql-snow .ql-toolbar button:hover .ql-fill,
    .quill-wrapper .ql-snow.ql-toolbar button.ql-active .ql-fill,
    .quill-wrapper .ql-snow .ql-toolbar button.ql-active .ql-fill {
        fill: #198BEA !important;
    }
    .quill-wrapper .ql-editor pre.ql-syntax {
        background-color: #18181b;
        color: #38bdf8;
        padding: 10px 14px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 0.8125rem;
    }
</style>
