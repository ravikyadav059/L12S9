<footer class="w-full bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800 font-sans mt-auto">
    <!-- Newsletter Section -->
    <div class="bg-brand-50 dark:bg-zinc-800/60 border-b border-zinc-200/80 dark:border-zinc-800 py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-center md:text-left">
                <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Subscribe us to get updated
                </h2>
            </div>
            
            <!-- Newsletter Form -->
            <form action="#" method="POST" class="w-full md:max-w-md">
                @csrf
                <div class="relative flex items-center">
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="Enter Your Email Address" 
                        required
                        class="w-full pl-4 pr-12 py-2.5 text-sm bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all"
                    />
                    <button 
                        type="submit" 
                        aria-label="Subscribe"
                        class="absolute right-1.5 top-1.5 bottom-1.5 px-3 flex items-center justify-center bg-[#092A45] hover:bg-brand text-white rounded-lg shadow-sm transition-all cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Footer Links Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-12">
            
            <!-- Col 1: Brand & Description -->
            <div class="lg:col-span-5 space-y-4">
                <div class="flex items-center justify-between gap-6 max-w-xs sm:max-w-sm">
                    <a href="{{ url('/') }}" class="inline-flex items-center" wire:navigate>
                        <x-app-logo class="h-9 w-auto" />
                    </a>
                    <a href="{{ Route::has('pdf.viewer') ? route('pdf.viewer') : '#' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center shrink-0">
                        <img src="{{ asset('images/assets/Startup India Logo.webp') }}" alt="Startup India Logo" class="h-9 w-auto object-contain">
                    </a>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed max-w-sm">
                    Scholar9 is aiming to empower the research community around the world with the help of technology & innovation. Scholar9 provides the required platform to Scholar for visibility & credibility.
                </p>
            </div>

            <!-- Col 2: Quicklinks -->
            <div class="lg:col-span-4 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white tracking-wider uppercase pb-2 relative inline-block">
                        QUICKLINKS
                        <span class="absolute bottom-0 left-0 w-8 h-0.5 bg-brand rounded-full"></span>
                    </h3>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <ul class="space-y-2.5">
                        <li>
                            <a href="{{ Route::has('whatisScholar9') ? route('whatisScholar9') : url('what-is-scholar9') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                What is Scholar9?
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('aboutus') ? route('aboutus') : url('about-us') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                About Us
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('missionVision') ? route('missionVision') : url('mission-vision') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                Mission Vision
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('contactus') ? route('contactus') : url('contact-us') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                Contact Us
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('docs') ? route('docs') : url('documentation') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                Documentation
                            </a>
                        </li>
                    </ul>

                    <ul class="space-y-2.5">
                        <li>
                            <a href="{{ Route::has('privacy') ? route('privacy') : url('privacy-policy') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                Privacy Policy
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('terms') ? route('terms') : url('terms-of-use') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                Terms of Use
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('blogs') ? route('blogs') : url('blogs') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                Blogs
                            </a>
                        </li>
                        <li>
                            <a href="{{ Route::has('faq') ? route('faq') : url('faq') }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors" wire:navigate>
                                FAQ
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Col 3: Contact Us -->
            <div class="lg:col-span-3 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white tracking-wider uppercase pb-2 relative inline-block">
                        CONTACT US
                        <span class="absolute bottom-0 left-0 w-8 h-0.5 bg-brand rounded-full"></span>
                    </h3>
                </div>

                <ul class="space-y-3">
                    <li>
                        <a href="tel:+918200385143" class="group flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors">
                            <span class="w-8 h-8 rounded-full bg-brand-50 dark:bg-brand-950/40 text-brand flex items-center justify-center shrink-0 group-hover:bg-brand group-hover:text-white group-hover:scale-105 transition-all">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-2.2 2.2a15.053 15.053 0 0 1-6.59-6.59l2.2-2.21a.96.96 0 0 0 .25-1A11.36 11.36 0 0 1 8.5 3.99c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-.99-1.11z"/>
                                </svg>
                            </span>
                            <span class="font-medium">+91 82003 85143</span>
                        </a>
                    </li>
                    <li>
                        <a href="mailto:hello@scholar9.com" class="group flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors">
                            <span class="w-8 h-8 rounded-full bg-brand-50 dark:bg-brand-950/40 text-brand flex items-center justify-center shrink-0 group-hover:bg-brand group-hover:text-white group-hover:scale-105 transition-all">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                            </span>
                            <span class="font-medium truncate">hello@scholar9.com</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.scholar9.com" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition-colors">
                            <span class="w-8 h-8 rounded-full bg-brand-50 dark:bg-brand-950/40 text-brand flex items-center justify-center shrink-0 group-hover:bg-brand group-hover:text-white group-hover:scale-105 transition-all">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                </svg>
                            </span>
                            <span class="font-medium">www.scholar9.com</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Copyright Bar -->
    <div class="border-t border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400">
            <p>&copy; {{ date('Y') }} Sequence Research & Development Pvt Ltd. All Rights Reserved.</p>
            
            <div class="flex items-center gap-4">
                <p class="text-zinc-400 text-xs">True scholar network</p>
                <button 
                    type="button" 
                    onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" 
                    class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-100 hover:bg-brand text-zinc-700 hover:text-white dark:bg-zinc-800 dark:hover:bg-brand dark:text-zinc-300 dark:hover:text-white text-xs font-semibold shadow-xs transition-all cursor-pointer"
                    title="Back to Top"
                    aria-label="Scroll to top"
                >
                    <span>Back to top</span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:-translate-y-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Scroll to Top & WhatsApp Action Buttons -->
    <div 
        x-data="{ showTopBtn: false }" 
        x-init="window.addEventListener('scroll', () => { showTopBtn = window.scrollY > 300 })"
        class="fixed bottom-6 right-6 z-40 flex flex-col items-center gap-3"
    >
        <!-- Floating Scroll to Top Button -->
        <button 
            x-show="showTopBtn" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-90"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-90"
            @click="window.scrollTo({ top: 0, behavior: 'smooth' })" 
            type="button" 
            aria-label="Scroll to top"
            class="flex items-center justify-center w-11 h-11 bg-white dark:bg-zinc-800 hover:bg-[#198BEA] dark:hover:bg-[#198BEA] text-zinc-700 dark:text-zinc-200 hover:text-white dark:hover:text-white border border-zinc-200 dark:border-zinc-700 hover:border-[#198BEA] rounded-full shadow-lg hover:shadow-xl hover:scale-110 active:scale-95 transition-all cursor-pointer"
            style="display: none;"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
            </svg>
        </button>

        <!-- Fixed WhatsApp Floating Action Button -->
        <a 
            href="https://wa.link/dz9m0p" 
            target="_blank" 
            rel="noopener noreferrer" 
            aria-label="Chat on WhatsApp"
            class="flex items-center justify-center w-12 h-12 bg-[#25D366] hover:bg-[#20bd5a] text-white rounded-full shadow-lg hover:shadow-xl hover:scale-110 active:scale-95 transition-all"
        >
            <svg class="w-7 h-7 fill-current" viewBox="0 0 24 24">
                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.044c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.159.57 4.185 1.564 5.939l-1.564 5.711 5.861-1.537c1.701.928 3.652 1.458 5.727 1.458 6.627 0 12-5.373 12-12 0-6.627-5.373-12-12-12z"/>
            </svg>
        </a>
    </div>
</footer>
