<x-layouts.app>
    <div x-data="{
        accepted: false,
        modalOpen: false,
        activeTerm: 1,
        termsRead: 1,
        toggleTerm(index) {
            this.activeTerm = this.activeTerm === index ? null : index;
            if (index > this.termsRead) {
                this.termsRead = index;
            }
        }
    }" class="bg-[#f8fafc] dark:bg-zinc-950 min-h-screen text-slate-800 dark:text-zinc-100 selection:bg-[#198BEA]/20 selection:text-[#198BEA]">

        <!-- ================= HERO SECTION: HIGH-CONVERTING ACADEMIC LANDING PAGE ================= -->
        <section class="relative overflow-hidden pt-12 sm:pt-20 lg:pt-24 pb-16 sm:pb-24 px-4 sm:px-6 lg:px-8 border-b border-slate-200/80 dark:border-zinc-800">
            <!-- Ambient Radial Glows -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden -z-10">
                <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[1000px] h-[550px] bg-gradient-to-b from-sky-200/50 via-blue-100/30 to-transparent dark:from-sky-950/30 dark:via-zinc-900/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/4 -right-40 w-[400px] h-[400px] bg-sky-200/30 dark:bg-sky-900/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/3 -left-40 w-[400px] h-[400px] bg-indigo-200/30 dark:bg-indigo-950/15 rounded-full blur-3xl"></div>
            </div>

            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    
                    <!-- Left Column: Copy & CTAs -->
                    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                        <!-- Top Badge -->
                        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-white dark:bg-zinc-900 border border-sky-200/90 dark:border-zinc-800 shadow-xs text-xs sm:text-sm font-semibold text-[#198BEA] dark:text-sky-400">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#198BEA] opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-[#198BEA]"></span>
                            </span>
                            <span>Join our global reviewer community</span>
                        </div>

                        <!-- Main Headline -->
                        <h1 class="text-4xl sm:text-6xl font-black text-slate-900 dark:text-white tracking-tight leading-[1.12]">
                            Become a <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#198BEA] via-sky-600 to-[#092A45] dark:from-sky-400 dark:via-sky-300 dark:to-blue-400">Research Paper Reviewer</span>
                        </h1>

                        <!-- Subtitle -->
                        <p class="text-base sm:text-lg text-slate-600 dark:text-zinc-300 max-w-2xl mx-auto lg:mx-0 leading-relaxed font-normal">
                            Empower the future of academia. Review cutting-edge papers, connect with experts, and advance your career—all from one intuitive platform.
                        </p>

                        <!-- Actions -->
                        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <a href="#enrollment" 
                               class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-[#198BEA] to-[#092A45] hover:from-[#1579cc] hover:to-[#0c375b] text-white font-bold text-base shadow-lg shadow-sky-600/25 hover:shadow-xl hover:shadow-sky-600/35 transform hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 cursor-pointer">
                                <span>Start Reviewing Today</span>
                                <i class="fas fa-arrow-down text-sm"></i>
                            </a>

                            <a href="#how-it-works" 
                               class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-2xl bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-200 font-semibold text-base border border-slate-200 dark:border-zinc-800 transition-all cursor-pointer">
                                <i class="fas fa-circle-play text-[#198BEA]"></i>
                                <span>How It Works</span>
                            </a>
                        </div>

                        <!-- Highlights Bar -->
                        <div class="pt-4 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs sm:text-sm text-slate-500 dark:text-zinc-400 font-medium border-t border-slate-200/60 dark:border-zinc-800/80">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-certificate text-emerald-500"></i>
                                <span>Peer Review Certificate</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-bolt text-amber-500"></i>
                                <span>Fast 48h Verification</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-shield-halved text-[#198BEA]"></i>
                                <span>Double-Blind Privacy</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Pure Academic Reviewer SVG Vector Illustration -->
                    <div class="lg:col-span-5 relative flex items-center justify-center">
                        <div class="relative w-full max-w-lg mx-auto">
                            <!-- Glow Backdrop Behind Illustration -->
                            <div class="absolute -inset-4 bg-gradient-to-tr from-[#198BEA]/30 via-sky-400/20 to-blue-600/20 rounded-full blur-3xl opacity-70 dark:opacity-40 pointer-events-none"></div>

                            <!-- SVG Illustration: Research Paper Peer Review -->
                            <svg viewBox="0 0 520 440" class="w-full h-auto relative drop-shadow-xl" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <!-- Linear Gradients -->
                                    <linearGradient id="primaryGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#198BEA" />
                                        <stop offset="100%" stop-color="#092A45" />
                                    </linearGradient>
                                    <linearGradient id="skyGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#38BDF8" />
                                        <stop offset="100%" stop-color="#198BEA" />
                                    </linearGradient>
                                    <linearGradient id="emeraldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#34D399" />
                                        <stop offset="100%" stop-color="#059669" />
                                    </linearGradient>
                                    <linearGradient id="goldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#FCD34D" />
                                        <stop offset="100%" stop-color="#F59E0B" />
                                    </linearGradient>
                                    <linearGradient id="paperGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="#FFFFFF" />
                                        <stop offset="100%" stop-color="#F8FAFC" />
                                    </linearGradient>
                                    <linearGradient id="glassGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#E0F2FE" stop-opacity="0.8" />
                                        <stop offset="100%" stop-color="#BAE6FD" stop-opacity="0.3" />
                                    </linearGradient>
                                    <!-- Drop Shadows -->
                                    <filter id="svgShadow" x="-10%" y="-10%" width="125%" height="125%">
                                        <feDropShadow dx="0" dy="12" stdDeviation="16" flood-color="#092A45" flood-opacity="0.12" />
                                    </filter>
                                    <filter id="badgeShadow" x="-15%" y="-15%" width="130%" height="130%">
                                        <feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="#092A45" flood-opacity="0.15" />
                                    </filter>
                                </defs>

                                <!-- Ambient Background Decorative Rings -->
                                <circle cx="260" cy="220" r="185" fill="none" stroke="#BAE6FD" stroke-width="1.5" stroke-dasharray="6 6" opacity="0.6" />
                                <circle cx="260" cy="220" r="135" fill="none" stroke="#E2E8F0" stroke-width="1" opacity="0.5" />

                                <!-- Floating Particle Nodes -->
                                <circle cx="95" cy="110" r="4" fill="#38BDF8" opacity="0.8" />
                                <circle cx="430" cy="90" r="5" fill="#198BEA" opacity="0.7" />
                                <circle cx="460" cy="330" r="4.5" fill="#34D399" opacity="0.8" />
                                <circle cx="70" cy="320" r="4" fill="#F59E0B" opacity="0.8" />

                                <!-- Background Manuscript Layer (Shadowed Paper) -->
                                <g transform="rotate(-6 230 230)">
                                    <rect x="130" y="70" width="220" height="290" rx="16" fill="#EEF2F6" stroke="#CBD5E1" stroke-width="1.5" opacity="0.6" />
                                </g>

                                <!-- Main Foreground Research Manuscript Paper -->
                                <g filter="url(#svgShadow)">
                                    <rect x="150" y="55" width="235" height="315" rx="18" fill="url(#paperGrad)" stroke="#E2E8F0" stroke-width="2" />
                                    
                                    <!-- Document Top Accent Header -->
                                    <path d="M 152 73 Q 152 57 168 57 L 367 57 Q 383 57 383 73 L 383 88 L 152 88 Z" fill="url(#primaryGrad)" />
                                    <circle cx="174" cy="72" r="5" fill="#38BDF8" />
                                    <circle cx="190" cy="72" r="5" fill="#BAE6FD" opacity="0.7" />
                                    <circle cx="206" cy="72" r="5" fill="#BAE6FD" opacity="0.4" />
                                    <rect x="235" y="69" width="125" height="7" rx="3.5" fill="#FFFFFF" opacity="0.9" />

                                    <!-- Manuscript Abstract & Title Lines -->
                                    <rect x="175" y="105" width="140" height="9" rx="4.5" fill="#092A45" />
                                    <rect x="175" y="122" width="185" height="5" rx="2.5" fill="#94A3B8" />
                                    <rect x="175" y="133" width="160" height="5" rx="2.5" fill="#CBD5E1" />

                                    <!-- Scientific Chart / Data Visualization Block inside Paper -->
                                    <rect x="175" y="152" width="185" height="68" rx="10" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="1" />
                                    <!-- Chart Grid Lines -->
                                    <line x1="188" y1="202" x2="348" y2="202" stroke="#CBD5E1" stroke-width="1" />
                                    <line x1="188" y1="182" x2="348" y2="182" stroke="#E2E8F0" stroke-width="1" stroke-dasharray="3 3" />
                                    <!-- Bar Chart Elements -->
                                    <rect x="198" y="180" width="14" height="22" rx="3" fill="#BAE6FD" />
                                    <rect x="220" y="168" width="14" height="34" rx="3" fill="#38BDF8" />
                                    <rect x="242" y="160" width="14" height="42" rx="3" fill="#198BEA" />
                                    <rect x="264" y="172" width="14" height="30" rx="3" fill="#60A5FA" />
                                    <!-- Line Graph Curve -->
                                    <path d="M 288 195 Q 308 165 325 170 T 345 158" fill="none" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round" />
                                    <circle cx="345" cy="158" r="3.5" fill="#F59E0B" />

                                    <!-- Paragraph Text Lines -->
                                    <rect x="175" y="235" width="185" height="5" rx="2.5" fill="#94A3B8" />
                                    <rect x="175" y="246" width="170" height="5" rx="2.5" fill="#CBD5E1" />
                                    <rect x="175" y="257" width="180" height="5" rx="2.5" fill="#CBD5E1" />
                                    <rect x="175" y="268" width="130" height="5" rx="2.5" fill="#E2E8F0" />

                                    <!-- Bottom Review Verification Stamp / Seal -->
                                    <g transform="translate(295, 290)">
                                        <rect x="0" y="0" width="75" height="24" rx="6" fill="#DCFCE7" stroke="#86EFAC" stroke-width="1" />
                                        <circle cx="12" cy="12" r="5" fill="#10B981" />
                                        <path d="M 9.5 12 L 11.5 14 L 14.5 10" fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <text x="24" y="16" font-family="Inter, sans-serif" font-size="9" font-weight="700" fill="#047857">VERIFIED</text>
                                    </g>
                                </g>

                                <!-- Magnifying Glass / Review Inspection Tool -->
                                <g filter="url(#badgeShadow)">
                                    <!-- Handle -->
                                    <rect x="360" y="250" width="16" height="60" rx="8" transform="rotate(-40 360 250)" fill="url(#primaryGrad)" stroke="#092A45" stroke-width="1.5" />
                                    <rect x="363" y="254" width="10" height="14" rx="3" transform="rotate(-40 360 250)" fill="#38BDF8" opacity="0.8" />
                                    <!-- Rim -->
                                    <circle cx="330" cy="215" r="46" fill="url(#glassGrad)" stroke="#198BEA" stroke-width="5" />
                                    <circle cx="330" cy="215" r="41" fill="none" stroke="#FFFFFF" stroke-width="2" opacity="0.6" />
                                    <!-- Reflection highlight in lens -->
                                    <path d="M 305 190 A 35 35 0 0 1 355 190" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" opacity="0.8" />
                                    <!-- Peer Review Checkmark Inside Lens -->
                                    <path d="M 315 215 L 326 226 L 348 202" fill="none" stroke="#198BEA" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />
                                </g>

                                <!-- Floating Badge 1: 5-Star Peer Review Score (Top Left) -->
                                <g filter="url(#badgeShadow)" transform="translate(60, 160)">
                                    <rect x="0" y="0" width="145" height="52" rx="14" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1.5" />
                                    <rect x="8" y="8" width="36" height="36" rx="10" fill="#FEF3C7" />
                                    <path d="M 26 15 L 29 22 L 36 22 L 30 26 L 32 33 L 26 29 L 20 33 L 22 26 L 16 22 L 23 22 Z" fill="#F59E0B" />
                                    <text x="52" y="24" font-family="Inter, sans-serif" font-size="11" font-weight="800" fill="#092A45">Peer Reviewed</text>
                                    <text x="52" y="38" font-family="Inter, sans-serif" font-size="9" font-weight="600" fill="#10B981">Top Quality 5.0 ★</text>
                                </g>

                                <!-- Floating Badge 2: Academic Shield & Confidentiality (Bottom Left) -->
                                <g filter="url(#badgeShadow)" transform="translate(85, 260)">
                                    <rect x="0" y="0" width="140" height="48" rx="14" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1.5" />
                                    <rect x="8" y="8" width="32" height="32" rx="10" fill="#E0F2FE" />
                                    <!-- Shield Icon -->
                                    <path d="M 24 14 L 32 17 V 23 C 32 27 28 31 24 33 C 20 31 16 27 16 23 V 17 Z" fill="#198BEA" />
                                    <path d="M 21 23 L 23 25 L 27 21" fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <text x="48" y="23" font-family="Inter, sans-serif" font-size="10" font-weight="700" fill="#092A45">Blind Review</text>
                                    <text x="48" y="36" font-family="Inter, sans-serif" font-size="8.5" font-weight="500" fill="#64748B">100% Confidential</text>
                                </g>

                                <!-- Floating Badge 3: Academic Scholar Cap (Top Right) -->
                                <g filter="url(#badgeShadow)" transform="translate(370, 75)">
                                    <circle cx="28" cy="28" r="28" fill="url(#primaryGrad)" />
                                    <!-- Mortarboard Icon -->
                                    <path d="M 28 17 L 42 24 L 28 31 L 14 24 Z" fill="#FFFFFF" />
                                    <path d="M 20 27 V 34 C 20 37 28 39 28 39 C 28 39 36 37 36 34 V 27" fill="none" stroke="#FFFFFF" stroke-width="1.5" stroke-linejoin="round" />
                                    <path d="M 40 24 V 33" stroke="#FCD34D" stroke-width="1.5" stroke-linecap="round" />
                                    <circle cx="40" cy="34" r="1.5" fill="#FCD34D" />
                                </g>

                                <!-- Floating Badge 4: Fast Reviewer Turnaround Pill (Bottom Right) -->
                                <g filter="url(#badgeShadow)" transform="translate(355, 340)">
                                    <rect x="0" y="0" width="135" height="42" rx="12" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1.5" />
                                    <circle cx="20" cy="21" r="12" fill="#FEF3C7" />
                                    <path d="M 20 15 V 21 L 24 23" fill="none" stroke="#D97706" stroke-width="1.5" stroke-linecap="round" />
                                    <text x="38" y="19" font-family="Inter, sans-serif" font-size="9.5" font-weight="800" fill="#092A45">Fast 48h</text>
                                    <text x="38" y="32" font-family="Inter, sans-serif" font-size="8.5" font-weight="600" fill="#F59E0B">Turnaround</text>
                                </g>
                            </svg>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ================= HOW IT WORKS SECTION ================= -->
        <section id="how-it-works" class="py-20 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto scroll-mt-8">
            <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-100/80 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800 text-xs font-bold text-[#198BEA] dark:text-sky-300 uppercase tracking-wider">
                    <i class="fas fa-bolt"></i>
                    <span>Streamlined Workflow</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    How It Works
                </h2>
                <p class="text-sm sm:text-base text-slate-600 dark:text-zinc-400">
                    Four effortless steps to launch your reviewing journey
                </p>
            </div>

            <!-- Steps Grid Container with Connecting Line Links -->
            <div class="relative">
                <!-- Steps Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative">
                    <!-- Step 1 -->
                    <div class="relative bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/50 dark:hover:border-sky-500/50 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-default">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900 group-hover:border-[#198BEA]/40 transition-colors">
                                    01
                                </span>
                                <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-zinc-800 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white group-hover:shadow-lg group-hover:shadow-[#198BEA]/30 group-hover:scale-105 transition-all duration-300">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors">Enroll as reviewer</h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                Enroll as a trusted reviewer by accepting the terms and conditions to start contributing to impactful research.
                            </p>
                        </div>

                        <!-- Connecting Line Link to Step 2 (Desktop) -->
                        <div class="hidden lg:flex items-center justify-center absolute -right-6 top-1/2 -translate-y-1/2 w-6 z-20 pointer-events-none text-[#198BEA] dark:text-sky-400 group-hover:translate-x-1 transition-transform duration-300" aria-hidden="true">
                            <svg class="w-6 h-4 overflow-visible" viewBox="0 0 24 16" fill="none">
                                <line x1="0" y1="8" x2="19" y2="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <polyline points="14,3.5 19.5,8 14,12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>

                        <!-- Connecting Line Link to Step 2 (Mobile / Tablet) -->
                        <div class="lg:hidden flex items-center justify-center absolute -bottom-6 left-1/2 -translate-x-1/2 h-6 z-20 pointer-events-none text-[#198BEA] dark:text-sky-400" aria-hidden="true">
                            <svg class="w-4 h-6 overflow-visible" viewBox="0 0 16 24" fill="none">
                                <line x1="8" y1="0" x2="8" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <polyline points="3.5,14 8,19.5 12.5,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/50 dark:hover:border-sky-500/50 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-default">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900 group-hover:border-[#198BEA]/40 transition-colors">
                                    02
                                </span>
                                <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-zinc-800 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white group-hover:shadow-lg group-hover:shadow-[#198BEA]/30 group-hover:scale-105 transition-all duration-300">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors">Start getting review requests</h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                Get personalized review requests based on your field and availability.
                            </p>
                        </div>

                        <!-- Connecting Line Link to Step 3 (Desktop) -->
                        <div class="hidden lg:flex items-center justify-center absolute -right-6 top-1/2 -translate-y-1/2 w-6 z-20 pointer-events-none text-[#198BEA] dark:text-sky-400 group-hover:translate-x-1 transition-transform duration-300" aria-hidden="true">
                            <svg class="w-6 h-4 overflow-visible" viewBox="0 0 24 16" fill="none">
                                <line x1="0" y1="8" x2="19" y2="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <polyline points="14,3.5 19.5,8 14,12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>

                        <!-- Connecting Line Link to Step 3 (Mobile / Tablet) -->
                        <div class="lg:hidden flex items-center justify-center absolute -bottom-6 left-1/2 -translate-x-1/2 h-6 z-20 pointer-events-none text-[#198BEA] dark:text-sky-400" aria-hidden="true">
                            <svg class="w-4 h-6 overflow-visible" viewBox="0 0 16 24" fill="none">
                                <line x1="8" y1="0" x2="8" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <polyline points="3.5,14 8,19.5 12.5,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/50 dark:hover:border-sky-500/50 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-default">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900 group-hover:border-[#198BEA]/40 transition-colors">
                                    03
                                </span>
                                <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-zinc-800 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white group-hover:shadow-lg group-hover:shadow-[#198BEA]/30 group-hover:scale-105 transition-all duration-300">
                                    <i class="fas fa-book-open-reader"></i>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors">Review papers within our system</h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                Use our intuitive tools to read, annotate, and evaluate manuscripts efficiently and effectively.
                            </p>
                        </div>

                        <!-- Connecting Line Link to Step 4 (Desktop) -->
                        <div class="hidden lg:flex items-center justify-center absolute -right-6 top-1/2 -translate-y-1/2 w-6 z-20 pointer-events-none text-[#198BEA] dark:text-sky-400 group-hover:translate-x-1 transition-transform duration-300" aria-hidden="true">
                            <svg class="w-6 h-4 overflow-visible" viewBox="0 0 24 16" fill="none">
                                <line x1="0" y1="8" x2="19" y2="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <polyline points="14,3.5 19.5,8 14,12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>

                        <!-- Connecting Line Link to Step 4 (Mobile / Tablet) -->
                        <div class="lg:hidden flex items-center justify-center absolute -bottom-6 left-1/2 -translate-x-1/2 h-6 z-20 pointer-events-none text-[#198BEA] dark:text-sky-400" aria-hidden="true">
                            <svg class="w-4 h-6 overflow-visible" viewBox="0 0 16 24" fill="none">
                                <line x1="8" y1="0" x2="8" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <polyline points="3.5,14 8,19.5 12.5,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/50 dark:hover:border-sky-500/50 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-default">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900 group-hover:border-[#198BEA]/40 transition-colors">
                                    04
                                </span>
                                <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-zinc-800 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white group-hover:shadow-lg group-hover:shadow-[#198BEA]/30 group-hover:scale-105 transition-all duration-300">
                                    <i class="fas fa-award"></i>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors">Submit review along with ratings</h3>
                            <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                Provide thoughtful feedback and ratings to help shape the future of academic and scientific research.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= TERMS & ENROLLMENT SECTION (2-COLUMN MODERN LAYOUT) ================= -->
        <section id="enrollment" class="scroll-mt-8 py-16 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-slate-200/80 dark:border-zinc-800">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Sidebar: Sticky Acceptance & Enrollment Card -->
                <div class="lg:col-span-4 lg:sticky lg:top-24 space-y-6">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 sm:p-8 border border-slate-200/90 dark:border-zinc-800 shadow-xl space-y-6">
                        
                        <!-- Header -->
                        <div class="space-y-2">
                            <div class="w-12 h-12 rounded-2xl bg-[#198BEA] text-white flex items-center justify-center text-xl shadow-md shadow-[#198BEA]/30">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">
                                Review Our Terms
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400">
                                Before enrolling, please review these essential guidelines to ensure a smooth experience.
                            </p>
                        </div>

                        <!-- Verification Guarantee -->
                        <div class="p-3.5 rounded-2xl bg-sky-50 dark:bg-sky-950/50 border border-sky-100 dark:border-sky-900 text-xs text-slate-700 dark:text-zinc-300 space-y-1.5">
                            <div class="flex items-center gap-2 font-bold text-[#198BEA] dark:text-sky-400">
                                <i class="fas fa-check-double"></i>
                                <span>Fast 48-Hour Approval</span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed">
                                Once accepted, your profile is routed to editorial review and approved typically within 48 hours.
                            </p>
                        </div>

                        <!-- Checkbox Form -->
                        <form id="accept-reviewer-form" class="space-y-5 pt-2">
                            <label class="flex items-start gap-3 cursor-pointer select-none group">
                                <input type="checkbox" 
                                       id="reviewer"
                                       name="terms"
                                       x-model="accepted"
                                       class="mt-1 w-5 h-5 rounded border-slate-300 dark:border-zinc-700 text-[#198BEA] focus:ring-[#198BEA] dark:bg-zinc-800 transition cursor-pointer">
                                <span class="text-xs sm:text-sm font-semibold text-slate-700 dark:text-zinc-200 group-hover:text-slate-900 dark:group-hover:text-white transition-colors leading-relaxed">
                                    I accept the terms and commit to upholding academic integrity and confidentiality.
                                </span>
                            </label>

                            <!-- Submit Button -->
                            <button type="button" 
                                    id="accept_reviewer"
                                    @click="if (accepted) { modalOpen = true; }"
                                    :disabled="!accepted"
                                    :class="accepted 
                                        ? 'bg-gradient-to-r from-[#198BEA] to-[#092A45] hover:from-[#1579cc] hover:to-[#0c375b] text-white shadow-lg shadow-sky-600/25 hover:shadow-xl cursor-pointer transform hover:-translate-y-0.5' 
                                        : 'bg-slate-200 dark:bg-zinc-800 text-slate-400 dark:text-zinc-600 cursor-not-allowed shadow-none'"
                                    class="w-full inline-flex items-center justify-center gap-2.5 py-3.5 px-6 rounded-2xl font-bold text-sm transition-all duration-200">
                                <span>Enroll as Reviewer</span>
                                <i class="fas fa-circle-check text-sm"></i>
                            </button>
                        </form>

                    </div>
                </div>

                <!-- Right Area: 12 Interactive Accordion Terms -->
                <div class="lg:col-span-8 space-y-3">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200/90 dark:border-zinc-800 shadow-sm overflow-hidden divide-y divide-slate-100 dark:divide-zinc-800">
                        
                        <!-- Term 1 -->
                        <div class="transition-colors" :class="activeTerm === 1 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(1)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">1. Acceptance of Terms</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 1 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 1" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>By enrolling as a reviewer on scholar9.com, you agree to comply with and be bound by these Terms and Conditions. If you do not agree with these terms, please do not use our platform.</p>
                            </div>
                        </div>

                        <!-- Term 2 -->
                        <div class="transition-colors" :class="activeTerm === 2 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(2)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">2. Eligibility</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 2 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 2" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>You must be a researcher, academician, or scholar to enroll as a reviewer on scholar9.com. You agree to provide accurate and up-to-date information during the enrollment process.</p>
                            </div>
                        </div>

                        <!-- Term 3 -->
                        <div class="transition-colors" :class="activeTerm === 3 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(3)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">3. Reviewer Responsibilities</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 3 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 3" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed space-y-2">
                                <p>As a reviewer on scholar9.com, you agree to:</p>
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>Conduct objective, thorough, and constructive peer reviews of research articles.</li>
                                    <li>Maintain the confidentiality of submitted manuscripts and any sensitive information.</li>
                                    <li>Submit your reviews within the agreed-upon timeframe.</li>
                                    <li>Avoid any conflicts of interest and disclose any potential conflicts when reviewing articles.</li>
                                    <li>Use the platform responsibly and not engage in any activities that could harm the platform or its users.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Term 4 -->
                        <div class="transition-colors" :class="activeTerm === 4 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(4)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">4. Account Security</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 4 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 4" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>You are responsible for maintaining the security of your account login credentials. You agree not to share your account information with others or engage in unauthorized access.</p>
                            </div>
                        </div>

                        <!-- Term 5 -->
                        <div class="transition-colors" :class="activeTerm === 5 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(5)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">5. Content Guidelines</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 5 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 5" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed space-y-2">
                                <p>You agree not to upload, post, or share any content that is:</p>
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>In violation of any intellectual property rights or copyrights.</li>
                                    <li>Unlawful, harmful, offensive, or violates the rights of others.</li>
                                    <li>Spam, phishing, or contains malware.</li>
                                    <li>False, misleading, or inaccurate.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Term 6 -->
                        <div class="transition-colors" :class="activeTerm === 6 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(6)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">6. Intellectual Property</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 6 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 6" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>All content and materials on scholar9.com, including text, graphics, logos, and software, are protected by intellectual property rights. You may not use, reproduce, or distribute these materials without explicit permission.</p>
                            </div>
                        </div>

                        <!-- Term 7 -->
                        <div class="transition-colors" :class="activeTerm === 7 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(7)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">7. Privacy Policy</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 7 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 7" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>By enrolling as a reviewer, you acknowledge and agree to our Privacy Policy, which governs the collection and use of your personal information.</p>
                            </div>
                        </div>

                        <!-- Term 8 -->
                        <div class="transition-colors" :class="activeTerm === 8 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(8)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">8. Termination</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 8 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 8" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>We reserve the right to terminate or suspend your account for any reason, including violation of these Terms and Conditions.</p>
                            </div>
                        </div>

                        <!-- Term 9 -->
                        <div class="transition-colors" :class="activeTerm === 9 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(9)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">9. Dispute Resolution</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 9 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 9" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>Any disputes or claims arising from the use of scholar9.com will be subject to resolution through arbitration or mediation, as detailed in our separate Dispute Resolution Policy.</p>
                            </div>
                        </div>

                        <!-- Term 10 -->
                        <div class="transition-colors" :class="activeTerm === 10 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(10)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">10. Limitation of Liability</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 10 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 10" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>Scholar9.com is not responsible for any loss, damages, or harm resulting from your use of the platform. We provide the platform as-is, without warranties of any kind.</p>
                            </div>
                        </div>

                        <!-- Term 11 -->
                        <div class="transition-colors" :class="activeTerm === 11 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(11)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">11. Changes to Terms and Conditions</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 11 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 11" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                                <p>We reserve the right to update and modify these Terms and Conditions at any time. It is your responsibility to review these terms periodically.</p>
                            </div>
                        </div>

                        <!-- Term 12 -->
                        <div class="transition-colors" :class="activeTerm === 12 ? 'bg-sky-50/50 dark:bg-zinc-800/50' : ''">
                            <button type="button" @click="toggleTerm(12)" 
                                    class="w-full px-6 sm:px-8 py-4.5 text-left flex items-center justify-between gap-4 font-bold text-slate-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors">
                                <span class="text-sm sm:text-base">12. Contact Information</span>
                                <i class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-200" :class="activeTerm === 12 ? 'rotate-180 text-[#198BEA]' : ''"></i>
                            </button>
                            <div x-show="activeTerm === 12" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed space-y-2">
                                <p>If you have any questions or concerns about these Terms and Conditions, please contact us at <a href="mailto:hello@scholar9.com" class="text-[#198BEA] hover:underline font-bold">hello@scholar9.com</a></p>
                                <p>By enrolling as a reviewer on scholar9.com, you acknowledge that you have read and agreed to these Terms and Conditions. If you do not agree with these terms, please do not enroll as a reviewer on our platform.</p>
                                <p class="text-[11px] text-slate-400 dark:text-zinc-500 italic">This template is a general starting point and may need to be tailored to your specific requirements and local legal regulations. It's crucial to consult with legal professionals to ensure that your terms and conditions are legally sound and provide the necessary protections for your platform and its users.</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </section>

        <!-- ================= CONFIRMATION MODAL (Matching Exact Design Specification) ================= -->
        <div id="reviewer-modalReviewerFirst" 
             x-show="modalOpen" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            
            <!-- Backdrop -->
            <div x-show="modalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="modalOpen = false"
                 class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"></div>

            <!-- Modal Content Card -->
            <div x-show="modalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative bg-white dark:bg-zinc-900 rounded-3xl max-w-md w-full p-7 sm:p-9 text-center shadow-2xl border border-sky-100 dark:border-zinc-800 z-10 space-y-6">
                
                <!-- Close Button -->
                <button @click="modalOpen = false" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition-colors cursor-pointer">
                    <i class="fas fa-times text-base"></i>
                </button>

                <!-- Icon Section -->
                <div class="flex justify-center pt-2">
                    <div class="relative w-24 h-24 rounded-full bg-gradient-to-tr from-sky-50 to-blue-100 dark:from-zinc-800 dark:to-zinc-700 flex items-center justify-center shadow-inner">
                        <i class="fas fa-cloud text-[#198BEA] text-4xl"></i>
                        <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-md border-2 border-white dark:border-zinc-900">
                            <i class="fas fa-file-lines text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Text Section -->
                <div class="space-y-3">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 text-sm mx-auto mb-1">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 id="modal-title" class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        Thanks for enrolling as reviewer!
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                        Our team will review your profile and do the needful to get it approved. It should usually get approved within 48 hours.
                    </p>
                </div>

                <!-- Action Button -->
                <div class="pt-2">
                    <a href="{{ url('reviewer/profile') }}" 
                       id="close-modalmodalReviewerFirst"
                       class="w-full inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-[#198BEA] to-[#092A45] hover:from-[#1579cc] hover:to-[#0c375b] text-white font-bold text-base shadow-lg shadow-sky-600/25 hover:shadow-xl transition-all duration-200 cursor-pointer">
                        <span>Next</span>
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>

                <!-- Footer Status Note -->
                <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-center gap-2 text-xs font-semibold text-slate-500 dark:text-zinc-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Approval typically within 48 hours</span>
                </div>
            </div>
        </div>

    </div>
</x-layouts.app>
