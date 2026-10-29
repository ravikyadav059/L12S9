@php
    $navItems = [
        ['id' => 'about', 'label' => 'About'],
        ['id' => 'experience', 'label' => 'Experience'],
        ['id' => 'education', 'label' => 'Education'],
        ['id' => 'publications', 'label' => 'Publications'],
        ['id' => 'projects', 'label' => 'Projects'],
        ['id' => 'conferences', 'label' => 'Conferences'],
        ['id' => 'certificates', 'label' => 'Certificates'],
        ['id' => 'achievements', 'label' => 'Achievements'],
        ['id' => 'thesis', 'label' => 'Thesis Guided'],
        ['id' => 'memberships', 'label' => 'Memberships'],
        ['id' => 'patents', 'label' => 'Patents'],
        ['id' => 'invited-positions', 'label' => 'Invited Position'],
        ['id' => 'editorial-roles', 'label' => 'Research Journals'],
    ];
@endphp

<div 
    x-data="{ 
        isVisible: false,
        activeSection: 'about',

        checkNavVisibility() {
            const sidebarNav = document.getElementById('sidebar-quick-nav');
            if (!sidebarNav) {
                this.isVisible = window.scrollY > 350;
                return;
            }
            const rect = sidebarNav.getBoundingClientRect();
            // Sidebar Quick Navigation is visible if its top is inside the viewport and its bottom hasn't scrolled past top header (64px)
            const isSidebarVisible = (rect.top < window.innerHeight && rect.bottom > 64);

            // Only show when sidebar Quick Navigation is NOT visible AND user has scrolled past hero banner
            this.isVisible = !isSidebarVisible && window.scrollY > 200;
        },

        init() {
            // Initial check
            this.checkNavVisibility();

            // 1. Window scroll listener (passive) for reliable visibility toggle
            window.addEventListener('scroll', () => {
                this.checkNavVisibility();
            }, { passive: true });

            // 2. Observe all section elements to highlight the active section link dynamically
            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.activeSection = entry.target.id;
                    }
                });
            }, {
                root: null,
                threshold: 0.2,
                rootMargin: '-100px 0px -40% 0px'
            });

            document.querySelectorAll('main > div[id], main > section[id]').forEach(section => {
                sectionObserver.observe(section);
            });
        },

        handleWheel(e) {
            // Enable horizontal mouse wheel scrolling across the tabs
            if (e.deltaY !== 0) {
                e.currentTarget.scrollLeft += e.deltaY * 1.5;
            }
        }
    }" 
    x-cloak
    x-show="isVisible"
    x-transition:enter="transition-all duration-300 ease-out"
    x-transition:enter-start="opacity-0 -translate-y-full pointer-events-none"
    x-transition:enter-end="opacity-100 translate-y-0 pointer-events-auto"
    x-transition:leave="transition-all duration-200 ease-in"
    x-transition:leave-start="opacity-100 translate-y-0 pointer-events-auto"
    x-transition:leave-end="opacity-0 -translate-y-full pointer-events-none"
    style="display: none;"
    class="fixed top-16 left-0 right-0 z-40 bg-white/95 dark:bg-[#111c26]/95 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800 shadow-xs"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav 
            @wheel.passive="handleWheel($event)"
            class="flex items-center gap-7 overflow-x-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden py-3 text-xs sm:text-sm font-medium select-none"
        >
            @foreach($navItems as $item)
                <a 
                    href="#{{ $item['id'] }}" 
                    @click="activeSection = '{{ $item['id'] }}'"
                    :class="activeSection === '{{ $item['id'] }}' 
                        ? 'text-brand font-bold border-b-2 border-brand pb-0.5' 
                        : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white pb-0.5'"
                    class="shrink-0 transition-all whitespace-nowrap"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</div>
