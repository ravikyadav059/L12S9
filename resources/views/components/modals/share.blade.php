<div 
    x-data="{
        isOpen: false,
        shareUrl: '',
        shareTitle: '',
        modalHeader: 'Share Publication',
        modalSubtitle: 'Share this research paper across networks',
        copied: false,
        openShare(url, title, header, subtitle, type) {
            this.shareUrl = url || window.location.href;
            this.shareTitle = title || document.title;
            const isJournal = type === 'journal' || (this.shareUrl && (this.shareUrl.includes('/journal/') || this.shareUrl.includes('/journals')));
            this.modalHeader = header || (isJournal ? 'Share Journal' : 'Share Publication');
            this.modalSubtitle = subtitle || (isJournal ? 'Share this academic journal across networks' : 'Share this research paper across networks');
            this.copied = false;
            this.isOpen = true;
        },
        copyToClipboard() {
            if (!this.shareUrl) return;
            navigator.clipboard.writeText(this.shareUrl);
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        }
    }"
    @open-share-modal.window="openShare($event.detail?.url, $event.detail?.title, $event.detail?.header, $event.detail?.subtitle, $event.detail?.type)"
    x-show="isOpen" 
    x-cloak 
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog" 
    aria-modal="true"
    @keydown.escape.window="isOpen = false"
>
    <!-- Backdrop Blur -->
    <div 
        x-show="isOpen"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-zinc-900/60 backdrop-blur-xs transition-opacity"
        @click="isOpen = false"
    ></div>

    <!-- Modal Dialog Box -->
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div 
            x-show="isOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200/90 dark:border-zinc-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6 sm:p-7 space-y-5"
            @click.away="isOpen = false"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#198BEA]/10 dark:bg-[#198BEA]/20 text-[#198BEA] flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white" x-text="modalHeader">
                            Share Publication
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400" x-text="modalSubtitle">
                            Share this research paper across networks
                        </p>
                    </div>
                </div>

                <!-- Close 'X' Button -->
                <button 
                    type="button" 
                    @click="isOpen = false"
                    class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 flex items-center justify-center transition-colors cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- 1. Copy Link Bar (First) -->
            <div class="space-y-1.5 pt-1">
                <label class="block text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Page Link</label>
                <div class="flex items-center gap-2 p-1.5 bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <input 
                        type="text" 
                        readonly 
                        :value="shareUrl" 
                        @click="$el.select()"
                        class="flex-1 bg-transparent px-3 text-xs sm:text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none truncate font-mono select-all cursor-pointer"
                    />
                    <button 
                        type="button" 
                        @click="copyToClipboard"
                        class="px-4 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 shrink-0 cursor-pointer"
                        :class="copied ? 'bg-emerald-500 text-white shadow-xs' : 'bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white shadow-xs'"
                    >
                        <template x-if="!copied">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span>Copy Link</span>
                            </div>
                        </template>
                        <template x-if="copied">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Copied!</span>
                            </div>
                        </template>
                    </button>
                </div>
            </div>

            <!-- 2. Social Icons Row (Below Page Link) -->
            <div class="space-y-2 pt-2">
                <label class="block text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Share via</label>
                <div class="grid grid-cols-5 gap-2 pt-1">
                    <!-- WhatsApp -->
                    <a 
                        :href="'https://api.whatsapp.com/send?text=' + encodeURIComponent(shareTitle + ' ' + shareUrl)" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="flex flex-col items-center gap-1.5 p-2 rounded-2xl hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition group cursor-pointer"
                    >
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#25D366] text-white flex items-center justify-center shadow-md shadow-emerald-500/25 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                        </div>
                        <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-400">WhatsApp</span>
                    </a>

                    <!-- X / Twitter -->
                    <a 
                        :href="'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareTitle) + '&url=' + encodeURIComponent(shareUrl)" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="flex flex-col items-center gap-1.5 p-2 rounded-2xl hover:bg-zinc-100 dark:hover:bg-zinc-800 transition group cursor-pointer"
                    >
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-black dark:bg-zinc-800 text-white flex items-center justify-center shadow-md shadow-black/20 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </div>
                        <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-400">X</span>
                    </a>

                    <!-- LinkedIn -->
                    <a 
                        :href="'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl)" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="flex flex-col items-center gap-1.5 p-2 rounded-2xl hover:bg-sky-50 dark:hover:bg-sky-950/40 transition group cursor-pointer"
                    >
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#0A66C2] text-white flex items-center justify-center shadow-md shadow-sky-600/25 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.45c-.9 0-1.63.73-1.63 1.63s.73 1.63 1.63 1.63 1.63-.73 1.63-1.63-.73-1.63-1.63-1.63z"/>
                            </svg>
                        </div>
                        <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-400">LinkedIn</span>
                    </a>

                    <!-- Facebook -->
                    <a 
                        :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl)" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="flex flex-col items-center gap-1.5 p-2 rounded-2xl hover:bg-blue-50 dark:hover:bg-blue-950/40 transition group cursor-pointer"
                    >
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#1877F2] text-white flex items-center justify-center shadow-md shadow-blue-600/25 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </div>
                        <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-400">Facebook</span>
                    </a>

                    <!-- Email -->
                    <a 
                        :href="'mailto:?subject=' + encodeURIComponent(shareTitle) + '&body=' + encodeURIComponent(shareTitle + '\n\n' + shareUrl)" 
                        class="flex flex-col items-center gap-1.5 p-2 rounded-2xl hover:bg-zinc-100 dark:hover:bg-zinc-800 transition group cursor-pointer"
                    >
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-zinc-600 dark:bg-zinc-700 text-white flex items-center justify-center shadow-md shadow-zinc-600/25 group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5 fill-none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-400">Email</span>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
