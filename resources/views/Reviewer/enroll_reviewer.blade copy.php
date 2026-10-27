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

                    <!-- Right Column: Interactive Review Mockup Card -->
                    <div class="lg:col-span-5 relative">
                        <div class="relative mx-auto max-w-md lg:max-w-none">
                            <!-- Glow Backdrop Behind Card -->
                            <div class="absolute -inset-1.5 bg-gradient-to-r from-[#198BEA] to-sky-400 rounded-3xl blur-xl opacity-30 dark:opacity-20 animate-pulse"></div>

                            <!-- Showcase Card -->
                            <div class="relative bg-white dark:bg-zinc-900 rounded-3xl border border-sky-100 dark:border-zinc-800 p-6 sm:p-7 shadow-2xl space-y-5">
                                
                                <!-- Card Header -->
                                <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/80 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-lg">
                                            <i class="fas fa-file-contract"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900 dark:text-white">Sample Review Assignment</p>
                                            <p class="text-[11px] text-slate-400">Manuscript #S9-2026-881</p>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold border border-emerald-200/80 dark:border-emerald-800">
                                        Ready to Review
                                    </span>
                                </div>

                                <!-- Manuscript Title -->
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-[#198BEA] uppercase tracking-wide">Computer Science & AI</p>
                                    <h4 class="text-sm font-bold text-slate-800 dark:text-zinc-200 leading-snug">
                                        Deep Attention Networks for Multimodal Knowledge Graph Discovery
                                    </h4>
                                </div>

                                <!-- Review Rating Parameters Preview -->
                                <div class="space-y-2.5 bg-slate-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-slate-100 dark:border-zinc-800">
                                    <div class="flex items-center justify-between text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                        <span>Originality & Rigor</span>
                                        <div class="flex text-amber-400 text-[11px] gap-0.5">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-stroke"></i>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-[#198BEA] h-1.5 rounded-full w-[90%]"></div>
                                    </div>

                                    <div class="flex items-center justify-between text-xs font-semibold text-slate-700 dark:text-zinc-300 pt-1">
                                        <span>Methodology & Analysis</span>
                                        <div class="flex text-amber-400 text-[11px] gap-0.5">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-emerald-500 h-1.5 rounded-full w-[95%]"></div>
                                    </div>
                                </div>

                                <!-- Bottom Status Bar -->
                                <div class="pt-1 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2 text-slate-500 dark:text-zinc-400">
                                        <i class="fas fa-shield-check text-[#198BEA]"></i>
                                        <span>Double-Blind Integrity</span>
                                    </div>
                                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Scholar9 Review Portal</span>
                                </div>
                            </div>
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

            <!-- Steps Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/40 dark:hover:border-sky-500/40 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900">
                                01
                            </span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-50 to-blue-100 dark:from-zinc-800 dark:to-zinc-700 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white transition-all shadow-inner">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Enroll as reviewer</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                            Enroll as a trusted reviewer by accepting the terms and conditions to start contributing to impactful research.
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/40 dark:hover:border-sky-500/40 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900">
                                02
                            </span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-50 to-blue-100 dark:from-zinc-800 dark:to-zinc-700 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white transition-all shadow-inner">
                                <i class="fas fa-envelope-open-text"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Start getting review requests</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                            Get personalized review requests based on your field and availability.
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/40 dark:hover:border-sky-500/40 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900">
                                03
                            </span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-50 to-blue-100 dark:from-zinc-800 dark:to-zinc-700 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white transition-all shadow-inner">
                                <i class="fas fa-book-open-reader"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Review papers within our system</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                            Use our intuitive tools to read, annotate, and evaluate manuscripts efficiently and effectively.
                        </p>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-7 border border-slate-200/80 dark:border-zinc-800 shadow-sm hover:shadow-xl hover:border-[#198BEA]/40 dark:hover:border-sky-500/40 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950 text-[#198BEA] dark:text-sky-400 font-extrabold text-sm border border-sky-100 dark:border-sky-900">
                                04
                            </span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-50 to-blue-100 dark:from-zinc-800 dark:to-zinc-700 text-[#198BEA] dark:text-sky-400 flex items-center justify-center text-xl group-hover:bg-[#198BEA] group-hover:text-white transition-all shadow-inner">
                                <i class="fas fa-award"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Submit review along with ratings</h3>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-zinc-400 leading-relaxed">
                            Provide thoughtful feedback and ratings to help shape the future of academic and scientific research.
                        </p>
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
                            <div x-show="activeTerm === 5" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed space-y-2">
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
                            <div x-show="activeTerm === 6" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
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
                            <div x-show="activeTerm === 7" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
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
                            <div x-show="activeTerm === 8" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
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
                            <div x-show="activeTerm === 9" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
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
                            <div x-show="activeTerm === 10" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
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
                            <div x-show="activeTerm === 11" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
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
                            <div x-show="activeTerm === 12" x-collapse x-cloak class="px-6 sm:px-8 pb-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed space-y-2">
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
