<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Database Fixing - Admin')] class extends Component {
    /**
     * List of available database table formatters & fixers.
     */
    public function getToolsProperty(): array
    {   return [
            [
                'id' => 'status',
                'table' => 'Publication',
                'title' => 'status',
                'description' => 'This tool scans all Publication records for legacy string-type status values (e.g. "1", "0", "published", "active", "pending") and converts them into standardized native MongoDB integers (1 for Active, 0 for Inactive) for high-performance indexing and consistent filtering.',
                'route' => route('admin.database-fixing.publication.status'),
            ],
            [
                'id' => 'registered_co_author',
                'table' => 'Publication',
                'title' => 'registered_co_author',
                'description' => 'This tool finds Publication records where the registered_co_author field stores legacy comma-delimited strings (e.g. ",65d3200...,64523...") or raw text, and auto-cleans leading commas, trims empty values, and converts them into a standardized MongoDB array of User ObjectIds (["65d3200...", "64523..."]) for faster queries and efficient author retrieval.',
                'route' => route('admin.database-fixing.publication.registered-co-author'),
            ],
            [
                'id' => 'unregistered_co_author',
                'table' => 'Publication',
                'title' => 'unregistered_co_author',
                'description' => 'This tool detects Publication records where the unregistered_co_author field stores plain text strings (e.g. "Dr. John Doe, Prof. Jane Smith") or empty strings, converting valid names into a standardized MongoDB array (["Dr. John Doe", "Prof. Jane Smith"]) or null.',
                'route' => route('admin.database-fixing.publication.unregistered-co-author'),
            ],
            [
                'id' => 'pub_orphan_journal',
                'table' => 'Publication',
                'title' => 'journal_title (Missing / Ghost Journal Inspector)',
                'description' => 'Scans Publication records where journal_title contains a reference to a ReviewerJournal that does not exist in the database (deleted/orphan references), displaying detailed diagnostics, fallback journal_name presence, and full document inspectability.',
                'route' => route('admin.database-fixing.publication.orphan-journal'),
            ],
            [
                'id' => 'role',
                'table' => 'RoleInResearchJournals',
                'title' => 'role',
                'description' => 'This tool finds RoleInResearchJournals records where the role field stores legacy raw string names (e.g. "Assistant Editor", "Reviewer", "Editor-in-Chief") and maps them to the corresponding standardized JournalRole MongoDB ObjectId for structured relationships and fast lookups.',
                'route' => route('admin.database-fixing.role-in-research-journals.role'),
            ],
            [
                'id' => 'req_registered_co_author',
                'table' => 'RequestReviewPaper',
                'title' => 'registered_co_author',
                'description' => 'This tool scans RequestReviewPaper records for legacy comma-delimited strings or raw text in registered_co_author and converts them into standardized native MongoDB arrays of User IDs or null.',
                'route' => route('admin.database-fixing.request-review-paper.registered-co-author'),
            ],
            [
                'id' => 'req_unregistered_co_author',
                'table' => 'RequestReviewPaper',
                'title' => 'unregistered_co_author',
                'description' => 'This tool converts plain text strings or comma-delimited names in unregistered_co_author into standardized native MongoDB arrays or null.',
                'route' => route('admin.database-fixing.request-review-paper.unregistered-co-author'),
            ],
            [
                'id' => 'req_authors_name_only',
                'table' => 'RequestReviewPaper',
                'title' => 'authors_name_only',
                'description' => 'This tool converts comma-delimited author names in authors_name_only into standardized native MongoDB arrays or null.',
                'route' => route('admin.database-fixing.request-review-paper.authors-name-only'),
            ],
            [
                'id' => 'req_paper_keywords',
                'table' => 'RequestReviewPaper',
                'title' => 'paper_keywords',
                'description' => 'This tool scans RequestReviewPaper records for comma or semicolon delimited keywords and converts them into clean native MongoDB arrays of keyword strings or null.',
                'route' => route('admin.database-fixing.request-review-paper.paper-keywords'),
            ],
            [
                'id' => 'req_review_parameters',
                'table' => 'RequestReviewPaper',
                'title' => 'review_parameters',
                'description' => 'This tool formats raw strings and serialized parameters in review_parameters into standardized native MongoDB arrays or null.',
                'route' => route('admin.database-fixing.request-review-paper.review-parameters'),
            ],
            [
                'id' => 'req_scholar_profiles',
                'table' => 'RequestReviewPaper',
                'title' => 'scholar_profiles',
                'description' => 'This tool converts raw text or comma-separated scholar profile links/IDs in scholar_profiles into standardized native MongoDB arrays or null.',
                'route' => route('admin.database-fixing.request-review-paper.scholar-profiles'),
            ],
            [
                'id' => 'rev_publications',
                'table' => 'ReviewerProfile',
                'title' => 'publication (Orphan Cleaner & Active/Inactive Counts)',
                'description' => 'Scans all ReviewerProfile records, detects and purges non-existent (ghost) publication IDs, preserves both active (status=1) and inactive (status=0) publications, and calculates standardized publication_active_count and publication_inactive_count fields.',
                'route' => route('admin.database-fixing.reviewer-profile.orphan-cleaner.publication'),
            ],
            [
                'id' => 'rev_experience',
                'table' => 'ReviewerProfile',
                'title' => 'experience (Orphan Cleaner)',
                'description' => 'Scans all ReviewerProfile records, detects and purges non-existent (ghost) experience IDs against the experience collection, and preserves both active and inactive existing experience records.',
                'route' => route('admin.database-fixing.reviewer-profile.orphan-cleaner.experience'),
            ],
            [
                'id' => 'rev_seminar',
                'table' => 'ReviewerProfile',
                'title' => 'seminar (Orphan Cleaner)',
                'description' => 'Scans all ReviewerProfile records, detects and purges non-existent (ghost) seminar IDs against the seminar collection, and preserves both active and inactive existing seminar records.',
                'route' => route('admin.database-fixing.reviewer-profile.orphan-cleaner.seminar'),
            ],
            [
                'id' => 'rev_role_in_research_journals',
                'table' => 'ReviewerProfile',
                'title' => 'RoleInResearchJournal (Orphan Cleaner)',
                'description' => 'Scans all ReviewerProfile records, detects and purges non-existent (ghost) journal role IDs against the role_in_research_journals collection, and preserves all valid records.',
                'route' => route('admin.database-fixing.reviewer-profile.orphan-cleaner.role-in-research-journal'),
            ],
            [
                'id' => 'rev_master',
                'table' => 'ReviewerProfile',
                'title' => 'All 12 Array Fields (Master Ingestion)',
                'description' => 'Comprehensive master tool that scans all ReviewerProfile records across 12 array fields (experience, education, publication, projects, seminar, certificate, phd, award, RoleInResearchJournal, invited_position, membership, patent) and standardizes them from JSON strings/delimiters into native MongoDB arrays.',
                'route' => route('admin.database-fixing.reviewer-profile.index'),
            ],
            [
                'id' => 'exp_dates',
                'table' => 'Experience',
                'title' => 'join_date & end_date ("Month Year")',
                'description' => 'Scans all Experience records and standardizes arbitrary legacy date strings (e.g. "2015-08-01", "08/2015", "Aug 2015") into strict "Month Year" format (e.g. "January 2026", "August 2015", "July 2026").',
                'route' => route('admin.database-fixing.experience.index'),
            ],
            [
                'id' => 'sem_month_year',
                'table' => 'Seminar',
                'title' => 'month_year ("Month Year")',
                'description' => 'Scans all Seminar records and standardizes arbitrary legacy date strings in the month_year field (e.g. "2015-08-01", "08/2015", "Aug 2015") into strict "Month Year" format (e.g. "January 2026", "August 2015") for consistent event timeline rendering.',
                'route' => route('admin.database-fixing.seminar.index'),
            ],
        ];
    }

    public function getTableStyle(string $table): array
    {
        return match ($table) {
            'Publication' => [
                'badge' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/70',
                'dot' => 'bg-blue-500',
                'title' => 'text-blue-950 dark:text-blue-100',
                'btn' => 'text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800 hover:bg-blue-600 hover:text-white dark:hover:bg-blue-600 hover:border-blue-600',
            ],
            'RoleInResearchJournals' => [
                'badge' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800/70',
                'dot' => 'bg-purple-500',
                'title' => 'text-purple-950 dark:text-purple-100',
                'btn' => 'text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800 hover:bg-purple-600 hover:text-white dark:hover:bg-purple-600 hover:border-purple-600',
            ],
            'RequestReviewPaper' => [
                'badge' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/70',
                'dot' => 'bg-amber-500',
                'title' => 'text-amber-950 dark:text-amber-100',
                'btn' => 'text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800 hover:bg-amber-600 hover:text-white dark:hover:bg-amber-600 hover:border-amber-600',
            ],
            'ReviewerProfile' => [
                'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/70',
                'dot' => 'bg-emerald-500',
                'title' => 'text-emerald-950 dark:text-emerald-100',
                'btn' => 'text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 hover:border-emerald-600',
            ],
            'Experience' => [
                'badge' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/70',
                'dot' => 'bg-rose-500',
                'title' => 'text-rose-950 dark:text-rose-100',
                'btn' => 'text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 hover:border-rose-600',
            ],
            'Seminar' => [
                'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800/70',
                'dot' => 'bg-indigo-500',
                'title' => 'text-indigo-950 dark:text-indigo-100',
                'btn' => 'text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-600 hover:border-indigo-600',
            ],
            default => [
                'badge' => 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700',
                'dot' => 'bg-zinc-500',
                'title' => 'text-zinc-900 dark:text-white',
                'btn' => 'text-zinc-800 dark:text-zinc-100 border-zinc-300 dark:border-zinc-700 hover:bg-brand hover:text-white hover:border-brand',
            ],
        };
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">
                Database Fixing & Schema Ingestion
            </h2>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Transform unformatted raw records, legacy imports, or batch data into standardized MongoDB schema.
            </p>
        </div>

        <!-- TABLE CATEGORIES COLOR LEGEND -->
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800">
                <span class="size-2 rounded-full bg-blue-500"></span>
                Publication
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800">
                <span class="size-2 rounded-full bg-purple-500"></span>
                RoleInResearchJournals
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800">
                <span class="size-2 rounded-full bg-amber-500"></span>
                RequestReviewPaper
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                ReviewerProfile
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800">
                <span class="size-2 rounded-full bg-rose-500"></span>
                Experience
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800">
                <span class="size-2 rounded-full bg-indigo-500"></span>
                Seminar
            </span>
        </div>
    </div>

    <!-- FORMATTERS DIRECTORY TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-900/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5 w-14">No</th>
                        <th class="px-5 py-3.5 w-52">Table Name</th>
                        <th class="px-5 py-3.5 w-72">Target Field</th>
                        <th class="px-5 py-3.5">Transformation Summary</th>
                        <th class="px-5 py-3.5 text-center w-24">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @foreach($this->tools as $index => $tool)
                        @php
                            $style = $this->getTableStyle($tool['table']);
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                            <!-- No -->
                            <td class="px-5 py-4 font-semibold text-zinc-400 dark:text-zinc-500">
                                {{ $index + 1 }}
                            </td>

                            <!-- Table Name with Color Badge -->
                            <td class="px-5 py-4 font-bold">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold border {{ $style['badge'] }}">
                                    <span class="size-1.5 rounded-full {{ $style['dot'] }}"></span>
                                    {{ $tool['table'] }}
                                </span>
                            </td>

                            <!-- Sub Title / Target Field -->
                            <td class="px-5 py-4 font-bold {{ $style['title'] }}">
                                <code class="font-mono px-2 py-0.5 rounded text-xs bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700">
                                    {{ $tool['title'] }}
                                </code>
                            </td>

                            <!-- Sub Description -->
                            <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300 leading-relaxed max-w-xl">
                                {{ $tool['description'] }}
                            </td>

                            <!-- Action Button: GO with harmonized hover -->
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <a 
                                    href="{{ $tool['route'] }}"
                                    wire:navigate
                                    class="inline-block px-4 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 border transition shadow-xs cursor-pointer tracking-wider uppercase {{ $style['btn'] }}"
                                >
                                    GO
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
