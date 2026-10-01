<!-- RIGHT CONTAINER: REUSABLE SIDEBAR BANNERS (MAX 320px WIDTH) -->
<div class="w-full lg:w-[320px] lg:max-w-[320px] shrink-0 space-y-6">
    
    <!-- 1. OJSCloud Banner Card -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#0088ff] to-[#0055cc] p-6 text-white shadow-xl">
        <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-xl"></div>
        
        <div class="relative z-10 space-y-3">
            <h3 class="text-xl sm:text-2xl font-extrabold uppercase leading-tight tracking-wide drop-shadow-xs">
                ESTABLISH YOUR OWN JOURNAL WITHOUT THE EXPENSE!
            </h3>
            <p class="text-xs sm:text-sm font-medium text-sky-100 leading-relaxed">
                OJSCloud offers a complete, free setup to get you publishing.
            </p>
            <div class="pt-2">
                <a 
                    href="#" 
                    class="inline-flex items-center gap-2 bg-[#22c55e] hover:bg-[#16a34a] text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-full shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-all"
                >
                    <span>Start Your Free Journal!</span>
                </a>
            </div>
        </div>

        <div class="mt-6 pt-4 flex items-end justify-between border-t border-white/20 relative z-10">
            <div class="w-12 h-12 rounded-full bg-red-600 text-white font-extrabold text-xs flex items-center justify-center shadow-lg border-2 border-white rotate-[-12deg]">
                FREE
            </div>
            <div class="text-right">
                <span class="text-xs font-bold tracking-widest text-sky-200 block uppercase">OJS CLOUD</span>
                <span class="text-[10px] text-sky-100 block">Open Journal System</span>
            </div>
        </div>
    </div>

    <!-- 2. LIST YOUR RESEARCH PAPER FREE Banner Card -->
    <div class="rounded-2xl bg-[#198BEA] p-5 text-white shadow-md space-y-3">
        <h3 class="text-sm sm:text-base font-extrabold uppercase tracking-wide">
            LIST YOUR RESEARCH PAPER FREE
        </h3>
        <div>
            <a href="{{ route('articles.deposit') }}" class="inline-block bg-[#22c55e] hover:bg-[#16a34a] text-white font-bold text-xs px-4 py-2 rounded-full shadow transition-all" wire:navigate>
                Submission article
            </a>
        </div>
    </div>

    <!-- 3. Journal Management Reimagined Card -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-3">
        <span class="px-2.5 py-1 text-[10px] font-extrabold tracking-wider uppercase bg-sky-50 dark:bg-sky-950/50 text-[#198BEA] rounded-full border border-sky-200/80">
            NEW FEATURE
        </span>
        <h4 class="text-base font-bold text-zinc-900 dark:text-white pt-1">
            Journal Management Reimagined.
        </h4>
        <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
            Seamless workflow, zero cost setup. The ultimate OJS alternative for modern publishers.
        </p>
    </div>

</div>
