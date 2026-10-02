@props([
    'show' => 'showAskModal',
    'title' => 'newTitle',
    'description' => 'newDescription',
    'tags' => 'newTags',
    'thumbnail' => 'newThumbnail',
    'submitAction' => 'submitQuestion',
    'closeAction' => '$set(\'showAskModal\', false)',
])

<!-- ================= ASK QUESTION POPUP MODAL (ALPINE.JS DRIVEN) ================= -->
<div 
    x-data="{
        show: @entangle($show)
    }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="show = false"
>
    <!-- Backdrop with blur -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="show = false"
    ></div>

    <!-- Modal Box Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-2xl text-left align-middle bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 overflow-hidden my-8"
            @click.stop
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 flex items-center justify-center text-[#198BEA] shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                            Ask a Question
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Share with the Scholar9 academic & research community
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    @click="show = false" 
                    class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="{{ $submitAction }}" class="p-6 space-y-4">
                <!-- Title -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Question Title <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="{{ $title }}"
                        placeholder="What's your question? Be specific (e.g., How to implement WebSockets in React?)" 
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all"
                    />
                    @error($title) <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Description (Rich Text / HTML Editor) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            Details &amp; Context <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] text-zinc-400">Supports rich HTML formatting</span>
                    </div>

                    <div 
                        x-data="{
                            wrapSelection(openTag, closeTag) {
                                const textarea = this.$refs.editorTextarea;
                                if (!textarea) return;
                                const start = textarea.selectionStart;
                                const end = textarea.selectionEnd;
                                const text = textarea.value;
                                const selectedText = text.substring(start, end);
                                const replacement = openTag + (selectedText || 'text') + closeTag;
                                textarea.value = text.substring(0, start) + replacement + text.substring(end);
                                textarea.selectionStart = start + openTag.length;
                                textarea.selectionEnd = start + replacement.length - closeTag.length;
                                textarea.focus();
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            },
                            insertList(type) {
                                const textarea = this.$refs.editorTextarea;
                                if (!textarea) return;
                                const start = textarea.selectionStart;
                                const end = textarea.selectionEnd;
                                const text = textarea.value;
                                const selected = text.substring(start, end) || 'Item';
                                const tag = type === 'ol' ? '<ol>\n  <li>' + selected + '</li>\n</ol>' : '<ul>\n  <li>' + selected + '</li>\n</ul>';
                                textarea.value = text.substring(0, start) + tag + text.substring(end);
                                textarea.focus();
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }"
                        class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden focus-within:border-[#198BEA] focus-within:ring-2 focus-within:ring-[#198BEA]/15 bg-zinc-50 dark:bg-zinc-800/80 transition-all"
                    >
                        <!-- Editor Toolbar -->
                        <div class="flex items-center flex-wrap gap-1 px-3 py-1.5 bg-zinc-100/90 dark:bg-zinc-800 border-b border-zinc-200/80 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 text-xs">
                            <button type="button" @click="wrapSelection('<strong>', '</strong>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-bold transition-colors cursor-pointer" title="Bold">
                                B
                            </button>
                            <button type="button" @click="wrapSelection('<em>', '</em>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] italic font-serif transition-colors cursor-pointer" title="Italic">
                                I
                            </button>
                            <button type="button" @click="wrapSelection('<u>', '</u>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] underline transition-colors cursor-pointer" title="Underline">
                                U
                            </button>
                            <button type="button" @click="wrapSelection('<h3>', '</h3>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-semibold text-[11px] transition-colors cursor-pointer" title="Heading 3">
                                H3
                            </button>
                            <div class="h-3.5 w-px bg-zinc-300 dark:bg-zinc-600 mx-1"></div>
                            <button type="button" @click="insertList('ul')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] transition-colors cursor-pointer" title="Bullet List">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                            </button>
                            <button type="button" @click="insertList('ol')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] transition-colors cursor-pointer" title="Numbered List">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 6h14M7 12h14M7 18h14M3 6h1v4M3 14h2v2H3v2h3"/></svg>
                            </button>
                            <button type="button" @click="wrapSelection('<a href=&quot;https://&quot; target=&quot;_blank&quot;>', '</a>')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] transition-colors cursor-pointer" title="Insert Link">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </button>
                            <button type="button" @click="wrapSelection('<code>', '</code>')" class="px-1.5 py-0.5 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-mono text-[11px] transition-colors cursor-pointer" title="Inline Code">
                                &lt;/&gt;
                            </button>
                            <button type="button" @click="wrapSelection('<blockquote>', '</blockquote>')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] transition-colors cursor-pointer" title="Quote">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
                            </button>
                        </div>

                        <!-- Textarea -->
                        <textarea 
                            x-ref="editorTextarea"
                            rows="5" 
                            wire:model="{{ $description }}"
                            placeholder="Provide all background information, formatted text, HTML, code examples, or research context..." 
                            class="w-full px-4 py-2.5 bg-transparent text-sm placeholder-zinc-400 focus:outline-hidden transition-all resize-y text-zinc-900 dark:text-zinc-100"
                        ></textarea>
                    </div>
                    @error($description) <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Tags -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Tags (comma separated)
                    </label>
                    <input 
                        type="text" 
                        wire:model="{{ $tags }}"
                        placeholder="e.g., react, typescript, machine-learning, statistics" 
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all"
                    />
                    <p class="text-[11px] text-zinc-400 mt-1">Add up to 5 keywords or academic fields</p>
                </div>

                <!-- Thumbnail Attachment Upload Box -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Attachments (optional thumbnail / chart)
                    </label>
                    <div class="relative border-2 border-dashed border-zinc-200 dark:border-zinc-700 hover:border-[#198BEA]/50 dark:hover:border-[#198BEA]/50 rounded-2xl p-5 text-center transition-colors bg-zinc-50/50 dark:bg-zinc-800/30">
                        <input 
                            type="file" 
                            wire:model="{{ $thumbnail }}"
                            accept="image/*"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        />
                        <div class="flex flex-col items-center justify-center pointer-events-none">
                            <svg class="w-8 h-8 text-zinc-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                Drag &amp; drop an image, or <span class="text-[#198BEA] font-bold">browse</span>
                            </p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">Supports PNG, JPG, GIF · Max 10MB</p>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button 
                        type="button" 
                        @click="show = false" 
                        class="px-5 py-2.5 text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs font-bold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98"
                    >
                        <span wire:loading.remove wire:target="{{ $submitAction }}">Post Question</span>
                        <span wire:loading wire:target="{{ $submitAction }}" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Posting...
                        </span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
