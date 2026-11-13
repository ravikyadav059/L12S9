<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.admin')] #[Title('Database Fixing - Admin')] class extends Component {
    /**
     * List of available database table formatters & fixers.
     */
    public function getToolsProperty(): array
    {
        return [
            // Publication Tools
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
                'id' => 'pub_reviewer_profile_sync',
                'table' => 'Publication',
                'title' => 'reviewer_profile & Co-Author Sync (Missing IDs, Ghost IDs & Counts)',
                'description' => 'Synchronizes reviewer_profile.publication arrays for user_id and all registered_co_author users in high-speed batches, purges ghost IDs, and recalculates publication_active_count & publication_inactive_count without modifying existing publications records.',
                'route' => route('admin.database-fixing.publication.reviewer-profile'),
            ],

            // RoleInResearchJournals Tools
            [
                'id' => 'role',
                'table' => 'RoleInResearchJournals',
                'title' => 'role',
                'description' => 'This tool finds RoleInResearchJournals records where the role field stores legacy raw string names (e.g. "Assistant Editor", "Reviewer", "Editor-in-Chief") and maps them to the corresponding standardized JournalRole MongoDB ObjectId for structured relationships and fast lookups.',
                'route' => route('admin.database-fixing.role-in-research-journals.role'),
            ],
            [
                'id' => 'role_journal_reviewer_profile_sync',
                'table' => 'RoleInResearchJournals',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between role_in_research_journals and reviewer_profile.RoleInResearchJournal: recovers missing user IDs from profiles (75 recoverable records), marks true orphans as status 0, adds unlinked records to user profiles, removes deleted ghost IDs (86 ghosts), and standardizes missing statuses.',
                'route' => route('admin.database-fixing.role-in-research-journals.reviewer-profile'),
            ],

            // RequestReviewPaper Tools
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

            // ReviewerProfile Tools
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
                'description' => 'Scans all ReviewerProfile records, detects and purges non-existent (ghost) experience IDs against the experience collection, and preserves all valid records.',
                'route' => route('admin.database-fixing.reviewer-profile.orphan-cleaner.experience'),
            ],
            [
                'id' => 'rev_seminar',
                'table' => 'ReviewerProfile',
                'title' => 'seminar (Orphan Cleaner)',
                'description' => 'Scans all ReviewerProfile records, detects and purges non-existent (ghost) seminar IDs against the seminar collection, and preserves all valid records.',
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
                'id' => 'rev_publications_alt',
                'table' => 'ReviewerProfile',
                'title' => 'reviewer-profile-publications (Direct Cleaner Shortcut)',
                'description' => 'Quick shortcut to inspect and clean reviewer profile publication relationships, orphan publication links, and recalculate profile publication metrics.',
                'route' => route('admin.database-fixing.reviewer-profile.publication'),
            ],

            // Experience Tools
            [
                'id' => 'exp_reviewer_profile_sync',
                'table' => 'Experience',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between experiences and reviewer profiles: adds missing experiences to user profiles, removes deleted ghost experience IDs, links missing user IDs to experiences, and sets missing status to 0.',
                'route' => route('admin.database-fixing.experience.reviewer-profile'),
            ],
            [
                'id' => 'exp_status',
                'table' => 'Experience',
                'title' => 'status (Missing / Unset Status Fixer)',
                'description' => 'Scans all Experience records for documents where the status field is missing, null, or unset, and standardizes them by setting native integer status 1 (Active). Also supports converting legacy string statuses into native integers.',
                'route' => route('admin.database-fixing.experience.status'),
            ],
            [
                'id' => 'exp_dates',
                'table' => 'Experience',
                'title' => 'join_date & end_date ("Month Year")',
                'description' => 'Scans all Experience records and standardizes arbitrary legacy date strings (e.g. "2015-08-01", "08/2015", "Aug 2015") into strict "Month Year" format (e.g. "January 2026", "August 2015", "July 2026").',
                'route' => route('admin.database-fixing.experience.index'),
            ],

            // Award Tools
            [
                'id' => 'award_reviewer_profile_sync',
                'table' => 'Award',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between awards and reviewer profiles: attaches missing user IDs to awards from reviewer profiles, adds unlinked awards to user profiles, removes deleted ghost award IDs, and standardizes status (1 if user assigned, 0 if orphan).',
                'route' => route('admin.database-fixing.award.reviewer-profile'),
            ],

            // PhD Tools
            [
                'id' => 'phd_reviewer_profile_sync',
                'table' => 'PhD',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between PhD theses and reviewer profiles: attaches missing user IDs to PhDs from reviewer profiles, adds unlinked PhDs to user profiles, removes deleted ghost PhD IDs, standardizes missing phd_status to Completed, standardizes empty institutions to NULL, and normalizes status (1 if user assigned, 0 if orphan).',
                'route' => route('admin.database-fixing.phd.reviewer-profile'),
            ],

            // Membership Tools
            [
                'id' => 'membership_organization_fixer',
                'table' => 'Membership',
                'title' => 'organization_id (Ghost IDs & Missing Organization Fixer)',
                'description' => 'Scans all Membership records in MongoDB for organization_id anomalies. Detects ghost organization IDs that reference non-existent organizations and records with missing or empty organization IDs, providing 1-click cleaning to NULL and an interactive organization assignment modal.',
                'route' => route('admin.database-fixing.membership.organization'),
            ],
            [
                'id' => 'membership_reviewer_profile_sync',
                'table' => 'Membership',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between memberships and reviewer profiles: recovers missing user IDs on memberships from reviewer profiles (5,362 recoverable records), adds unlinked memberships to user profiles, removes deleted ghost membership IDs, and standardizes status (1 if user assigned, 0 if orphan).',
                'route' => route('admin.database-fixing.membership.reviewer-profile'),
            ],

            // Patent Tools
            [
                'id' => 'patent_category_fixer',
                'table' => 'Patent',
                'title' => 'category_id (Category Fixer & Standardizer)',
                'description' => 'Scans all Patent documents in MongoDB to validate category_id ObjectIds, auto-match legacy title strings against Category titles, detect ghost references, and assign missing categories individually or in bulk.',
                'route' => route('admin.database-fixing.patent.category'),
            ],
            [
                'id' => 'patent_reviewer_profile_sync',
                'table' => 'Patent',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between patents and reviewer profiles: recovers missing user IDs on patents from reviewer profiles, adds unlinked patents to user profiles, removes deleted ghost patent IDs, and standardizes status (1 if user assigned, 0 if orphan).',
                'route' => route('admin.database-fixing.patent.reviewer-profile'),
            ],

            // Education Tools
            [
                'id' => 'edu_reviewer_profile_sync',
                'table' => 'Education',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between educations and reviewer profiles: recovers missing user IDs on educations from reviewer profiles, adds unlinked educations to user profiles, removes deleted ghost education IDs, and standardizes status (1 if user assigned, 0 if orphan).',
                'route' => route('admin.database-fixing.education.reviewer-profile'),
            ],
            [
                'id' => 'edu_degree_type',
                'table' => 'Education',
                'title' => 'degree_type (Legacy Text to DegreeType ObjectId)',
                'description' => 'Scans all Education records for documents where degree_type stores a raw string title (e.g. "Mastars", "BE", "Diploma", "M.Tech") instead of an ObjectId, auto-matches against the degree_type collection case-insensitively, and maps them to their standardized MongoDB ObjectId.',
                'route' => route('admin.database-fixing.education.degree-type'),
            ],
            [
                'id' => 'edu_field_of_study',
                'table' => 'Education',
                'title' => 'field_of_study (Legacy Text to FieldOfStudy ObjectId)',
                'description' => 'Scans all Education records for documents where field_of_study stores a raw string title (e.g. "Commerce", "Law", "Computer Engineering") instead of an ObjectId, auto-matches against the field_of_study collection case-insensitively, and maps them to their standardized MongoDB ObjectId.',
                'route' => route('admin.database-fixing.education.field-of-study'),
            ],
            [
                'id' => 'edu_grade',
                'table' => 'Education',
                'title' => 'grade (Legacy Text to Grade ObjectId)',
                'description' => 'Scans all Education records for documents where grade stores a raw string title (e.g. "A+", "First Class", "Distinction") instead of an ObjectId, auto-matches against the grade collection case-insensitively, and maps them to their standardized MongoDB ObjectId.',
                'route' => route('admin.database-fixing.education.grade'),
            ],
            [
                'id' => 'edu_institution',
                'table' => 'Education',
                'title' => 'institution_id (Legacy Raw Text to Organization ObjectId)',
                'description' => 'Scans all Education records for documents where institution_id contains a legacy raw text university/college name rather than a 24-character hexadecimal ObjectId, matches it against the organization collection, and links the standardized ObjectId.',
                'route' => route('admin.database-fixing.education.institution'),
            ],

            // Seminar Tools
            [
                'id' => 'seminar_dates',
                'table' => 'Seminar',
                'title' => 'start_date & end_date ("Month Year")',
                'description' => 'Scans all Seminar records in MongoDB and standardizes arbitrary legacy date strings into uniform "Month Year" format.',
                'route' => route('admin.database-fixing.seminar.index'),
            ],
            [
                'id' => 'seminar_reviewer_profile_sync',
                'table' => 'Seminar',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between seminars and reviewer profiles: recovers missing user IDs on seminars from reviewer profiles (23,611 recoverable records), adds unlinked seminars to user profiles, removes deleted ghost seminar IDs, and standardizes status (1 if user assigned, 0 if orphan).',
                'route' => route('admin.database-fixing.seminar.reviewer-profile'),
            ],

            // Question Tools
            [
                'id' => 'question_counts',
                'table' => 'Question',
                'title' => 'answer_count & votes_count (Count Sync & Recalculator)',
                'description' => 'Scans all Question records in MongoDB, checks for out-of-sync or legacy null/string answer_count, votes_count, upvotes_count, and downvotes_count against actual records in the answers and votes collections, and synchronizes them with precise native integer counts.',
                'route' => route('admin.database-fixing.question.counts'),
            ],
            [
                'id' => 'question_slug',
                'table' => 'Question',
                'title' => 'slug & title (Duplicate Slug & Title Resolver)',
                'description' => 'Scans Question records in MongoDB for identical slugs or duplicate titles across documents, groups collisions into 3 categories (Same Title & Same Slug, Same Title Only, Same Slug Only), and resolves conflicts by assigning clean unique suffixes (e.g. "slug-1", "slug-2").',
                'route' => route('admin.database-fixing.question.slug'),
            ],

            // Project Tools
            [
                'id' => 'project_registered_contributors',
                'table' => 'Project',
                'title' => 'registered_contributors',
                'description' => 'This tool scans all Project records in MongoDB where registered_contributors is stored as a legacy comma-delimited string (e.g. ",645a90...,65bc12...") or plain text, cleans leading commas and empty values, and standardizes them into native MongoDB BSON arrays of User ObjectIds or null for optimal query performance.',
                'route' => route('admin.database-fixing.project.registered-contributors'),
            ],

            // InvitedPosition Tools
            [
                'id' => 'invited_position_org_fixer',
                'table' => 'InvitedPosition',
                'title' => 'organization_id (Ghost IDs & Missing Organization Fixer)',
                'description' => 'Scans all Invited Position records in MongoDB for organization_id anomalies. Detects ghost references pointing to non-existent organizations and records with null/empty organization IDs, auto-matches suggestions from website URLs, and allows 1-click cleaning or interactive assignment from 37,000+ organizations.',
                'route' => route('admin.database-fixing.invited-position.organization'),
            ],
            [
                'id' => 'invited_position_reviewer_profile_sync',
                'table' => 'InvitedPosition',
                'title' => 'reviewer_profile & user_id Sync (Missing IDs, Ghost IDs & Status Fixer)',
                'description' => 'Fixes connection problems between invited positions and reviewer profiles: recovers missing user IDs on invited positions from reviewer profiles (372 recoverable records), marks true orphans as status 0, removes deleted ghost position IDs, and standardizes statuses.',
                'route' => route('admin.database-fixing.invited-position.reviewer-profile'),
            ],
        ];
    }

    /**
     * Group tools by Table Name.
     *
     * @return array<string, list<array>>
     */
    public function getGroupedToolsProperty(): array
    {
        $grouped = [];
        foreach ($this->tools as $tool) {
            $grouped[$tool['table']][] = $tool;
        }

        return $grouped;
    }

    /**
     * Return distinct, non-overlapping color palettes for each of the 12 tables.
     */
    public function getTableStyle(string $table): array
    {
        return match ($table) {
            'Question' => [
                'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-800',
                'dot' => 'bg-cyan-500',
                'title' => 'text-cyan-950 dark:text-cyan-100',
                'btn' => 'text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800 hover:bg-cyan-600 hover:text-white dark:hover:bg-cyan-600 hover:border-cyan-600',
                'header_bg' => 'bg-cyan-50/70 dark:bg-cyan-950/30 border-cyan-200/80 dark:border-cyan-800/60',
                'border' => 'border-cyan-200 dark:border-cyan-800/70',
                'hover_ring' => 'focus:ring-cyan-400',
            ],
            'Publication' => [
                'badge' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
                'dot' => 'bg-blue-500',
                'title' => 'text-blue-950 dark:text-blue-100',
                'btn' => 'text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800 hover:bg-blue-600 hover:text-white dark:hover:bg-blue-600 hover:border-blue-600',
                'header_bg' => 'bg-blue-50/70 dark:bg-blue-950/30 border-blue-200/80 dark:border-blue-800/60',
                'border' => 'border-blue-200 dark:border-blue-800/70',
                'hover_ring' => 'focus:ring-blue-400',
            ],
            'RoleInResearchJournals' => [
                'badge' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800',
                'dot' => 'bg-purple-500',
                'title' => 'text-purple-950 dark:text-purple-100',
                'btn' => 'text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800 hover:bg-purple-600 hover:text-white dark:hover:bg-purple-600 hover:border-purple-600',
                'header_bg' => 'bg-purple-50/70 dark:bg-purple-950/30 border-purple-200/80 dark:border-purple-800/60',
                'border' => 'border-purple-200 dark:border-purple-800/70',
                'hover_ring' => 'focus:ring-purple-400',
            ],
            'RequestReviewPaper' => [
                'badge' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                'dot' => 'bg-amber-500',
                'title' => 'text-amber-950 dark:text-amber-100',
                'btn' => 'text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800 hover:bg-amber-600 hover:text-white dark:hover:bg-amber-600 hover:border-amber-600',
                'header_bg' => 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-200/80 dark:border-amber-800/60',
                'border' => 'border-amber-200 dark:border-amber-800/70',
                'hover_ring' => 'focus:ring-amber-400',
            ],
            'ReviewerProfile' => [
                'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                'dot' => 'bg-emerald-500',
                'title' => 'text-emerald-950 dark:text-emerald-100',
                'btn' => 'text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 hover:border-emerald-600',
                'header_bg' => 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-200/80 dark:border-emerald-800/60',
                'border' => 'border-emerald-200 dark:border-emerald-800/70',
                'hover_ring' => 'focus:ring-emerald-400',
            ],
            'Experience' => [
                'badge' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
                'dot' => 'bg-rose-500',
                'title' => 'text-rose-950 dark:text-rose-100',
                'btn' => 'text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 hover:border-rose-600',
                'header_bg' => 'bg-rose-50/70 dark:bg-rose-950/30 border-rose-200/80 dark:border-rose-800/60',
                'border' => 'border-rose-200 dark:border-rose-800/70',
                'hover_ring' => 'focus:ring-rose-400',
            ],
            'Education' => [
                'badge' => 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800',
                'dot' => 'bg-teal-500',
                'title' => 'text-teal-950 dark:text-teal-100',
                'btn' => 'text-teal-700 dark:text-teal-300 border-teal-200 dark:border-teal-800 hover:bg-teal-600 hover:text-white dark:hover:bg-teal-600 hover:border-teal-600',
                'header_bg' => 'bg-teal-50/70 dark:bg-teal-950/30 border-teal-200/80 dark:border-teal-800/60',
                'border' => 'border-teal-200 dark:border-teal-800/70',
                'hover_ring' => 'focus:ring-teal-400',
            ],
            'Seminar' => [
                'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800',
                'dot' => 'bg-indigo-500',
                'title' => 'text-indigo-950 dark:text-indigo-100',
                'btn' => 'text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-600 hover:border-indigo-600',
                'header_bg' => 'bg-indigo-50/70 dark:bg-indigo-950/30 border-indigo-200/80 dark:border-indigo-800/60',
                'border' => 'border-indigo-200 dark:border-indigo-800/70',
                'hover_ring' => 'focus:ring-indigo-400',
            ],
            'Award' => [
                'badge' => 'bg-orange-50 text-orange-800 border-orange-300 dark:bg-orange-950/50 dark:text-orange-200 dark:border-orange-700',
                'dot' => 'bg-orange-500',
                'title' => 'text-orange-950 dark:text-orange-100',
                'btn' => 'text-orange-800 dark:text-orange-200 border-orange-300 dark:border-orange-700 hover:bg-orange-600 hover:text-white dark:hover:bg-orange-600 hover:border-orange-600',
                'header_bg' => 'bg-orange-50/70 dark:bg-orange-950/30 border-orange-300/80 dark:border-orange-700/60',
                'border' => 'border-orange-300 dark:border-orange-700',
                'hover_ring' => 'focus:ring-orange-400',
            ],
            'PhD' => [
                'badge' => 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-800',
                'dot' => 'bg-violet-500',
                'title' => 'text-violet-950 dark:text-violet-100',
                'btn' => 'text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-800 hover:bg-violet-600 hover:text-white dark:hover:bg-violet-600 hover:border-violet-600',
                'header_bg' => 'bg-violet-50/70 dark:bg-violet-950/30 border-violet-200/80 dark:border-violet-800/60',
                'border' => 'border-violet-200 dark:border-violet-800/70',
                'hover_ring' => 'focus:ring-violet-400',
            ],
            'Project' => [
                'badge' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800',
                'dot' => 'bg-sky-500',
                'title' => 'text-sky-950 dark:text-sky-100',
                'btn' => 'text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800 hover:bg-sky-600 hover:text-white dark:hover:bg-sky-600 hover:border-sky-600',
                'header_bg' => 'bg-sky-50/70 dark:bg-sky-950/30 border-sky-200/80 dark:border-sky-800/60',
                'border' => 'border-sky-200 dark:border-sky-800/70',
                'hover_ring' => 'focus:ring-sky-400',
            ],
            'Membership' => [
                'badge' => 'bg-lime-50 text-lime-800 border-lime-300 dark:bg-lime-950/40 dark:text-lime-300 dark:border-lime-800',
                'dot' => 'bg-lime-500',
                'title' => 'text-lime-950 dark:text-lime-100',
                'btn' => 'text-lime-800 dark:text-lime-300 border-lime-300 dark:border-lime-800 hover:bg-lime-600 hover:text-white dark:hover:bg-lime-600 hover:border-lime-600',
                'header_bg' => 'bg-lime-50/70 dark:bg-lime-950/30 border-lime-300/80 dark:border-lime-800/60',
                'border' => 'border-lime-300 dark:border-lime-800/70',
                'hover_ring' => 'focus:ring-lime-400',
            ],
            'Patent' => [
                'badge' => 'bg-fuchsia-50 text-fuchsia-800 border-fuchsia-200 dark:bg-fuchsia-950/40 dark:text-fuchsia-300 dark:border-fuchsia-800',
                'dot' => 'bg-fuchsia-500',
                'title' => 'text-fuchsia-950 dark:text-fuchsia-100',
                'btn' => 'text-fuchsia-800 dark:text-fuchsia-300 border-fuchsia-200 dark:border-fuchsia-800 hover:bg-fuchsia-600 hover:text-white dark:hover:bg-fuchsia-600 hover:border-fuchsia-600',
                'header_bg' => 'bg-fuchsia-50/70 dark:bg-fuchsia-950/30 border-fuchsia-200/80 dark:border-fuchsia-800/60',
                'border' => 'border-fuchsia-200 dark:border-fuchsia-800/70',
                'hover_ring' => 'focus:ring-fuchsia-400',
            ],
            'Certificate' => [
                'badge' => 'bg-pink-50 text-pink-700 border-pink-200 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-800',
                'dot' => 'bg-pink-500',
                'title' => 'text-pink-950 dark:text-pink-100',
                'btn' => 'text-pink-700 dark:text-pink-300 border-pink-200 dark:border-pink-800 hover:bg-pink-600 hover:text-white dark:hover:bg-pink-600 hover:border-pink-600',
                'header_bg' => 'bg-pink-50/70 dark:bg-pink-950/30 border-pink-200/80 dark:border-pink-800/60',
                'border' => 'border-pink-200 dark:border-pink-800/70',
                'hover_ring' => 'focus:ring-pink-400',
            ],
            'InvitedPosition' => [
                'badge' => 'bg-yellow-50 text-yellow-800 border-yellow-300 dark:bg-yellow-950/50 dark:text-yellow-200 dark:border-yellow-700',
                'dot' => 'bg-yellow-500',
                'title' => 'text-yellow-950 dark:text-yellow-100',
                'btn' => 'text-yellow-800 dark:text-yellow-200 border-yellow-300 dark:border-yellow-700 hover:bg-yellow-600 hover:text-white dark:hover:bg-yellow-600 hover:border-yellow-600',
                'header_bg' => 'bg-yellow-50/70 dark:bg-yellow-950/30 border-yellow-300/80 dark:border-yellow-700/60',
                'border' => 'border-yellow-300 dark:border-yellow-700',
                'hover_ring' => 'focus:ring-yellow-400',
            ],
            default => [
                'badge' => 'bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700',
                'dot' => 'bg-zinc-500',
                'title' => 'text-zinc-900 dark:text-white',
                'btn' => 'text-zinc-800 dark:text-zinc-100 border-zinc-300 dark:border-zinc-700 hover:bg-brand hover:text-white hover:border-brand',
                'header_bg' => 'bg-zinc-50 dark:bg-zinc-900/40 border-zinc-200 dark:border-zinc-800',
                'border' => 'border-zinc-200 dark:border-zinc-800',
                'hover_ring' => 'focus:ring-zinc-400',
            ],
        };
    }
}; ?>

<div 
    x-data="{
        openSections: {
            @foreach(array_keys($this->groupedTools) as $idx => $t)
                '{{ $t }}': {{ $idx === 0 ? 'true' : 'false' }},
            @endforeach
        },
        toggleSection(table) {
            this.openSections[table] = !this.openSections[table];
        },
        toggleAll(state) {
            for (let key in this.openSections) {
                this.openSections[key] = state;
            }
        },
        scrollToSection(table) {
            // Open the target section
            this.openSections[table] = true;
            setTimeout(() => {
                const el = document.getElementById('section-' + table);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    // Highlight pulse feedback
                    el.classList.add('ring-4', 'ring-brand/40', 'ring-offset-2');
                    setTimeout(() => el.classList.remove('ring-4', 'ring-brand/40', 'ring-offset-2'), 2000);
                }
            }, 60);
        }
    }" 
    class="space-y-6"
>
    <!-- PAGE HEADER -->
    <div class="bg-white dark:bg-zinc-800/80 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2.5">
            <flux:icon.circle-stack class="size-6 text-brand" />
            <span>Database Fixing &amp; Schema Ingestion</span>
        </h2>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
            Transform unformatted raw records, legacy imports, or batch data into standardized MongoDB schema.
        </p>
    </div>

    <!-- TABLE CATEGORIES QUICK JUMP BAR -->
    <div class="bg-white dark:bg-zinc-800/80 p-4 sm:p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
        <div class="flex items-center gap-2.5 flex-wrap">
            @foreach(array_keys($this->groupedTools) as $table)
                @php
                    $style = $this->getTableStyle($table);
                    $count = count($this->groupedTools[$table]);
                @endphp
                <button
                    type="button"
                    @click="scrollToSection('{{ $table }}')"
                    title="Click to jump directly to {{ $table }}"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs sm:text-sm font-bold border transition-all cursor-pointer shadow-2xs hover:scale-105 active:scale-95 {{ $style['badge'] }}"
                >
                    <span class="size-2 rounded-full {{ $style['dot'] }}"></span>
                    <span>{{ $table }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-xs font-semibold bg-black/10 dark:bg-white/10 font-mono">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <!-- GLOBAL CONTROLS & SUMMARY BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium flex items-center gap-2">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-zinc-800 dark:text-zinc-200">
                {{ count($this->tools) }} Tools
            </span>
            <span>across</span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-zinc-800 dark:text-zinc-200">
                {{ count($this->groupedTools) }} Database Tables
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button 
                type="button" 
                @click="toggleAll(true)" 
                class="px-3 py-1 rounded-lg text-xs font-semibold border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
            >
                Expand All
            </button>
            <button 
                type="button" 
                @click="toggleAll(false)" 
                class="px-3 py-1 rounded-lg text-xs font-semibold border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
            >
                Collapse All
            </button>
        </div>
    </div>

    <!-- GROUPED MAIN TABLES & SUB-TOOLS (COLLAPSIBLE WITH INDIVIDUAL COLORS) -->
    <div class="space-y-4">
        @php
            $globalIndex = 0;
        @endphp

        @foreach($this->groupedTools as $tableName => $tableTools)
            @php
                $style = $this->getTableStyle($tableName);
            @endphp
            <div 
                id="section-{{ $tableName }}"
                class="bg-white dark:bg-[#111c26] rounded-2xl border transition-all duration-300 shadow-xs overflow-hidden scroll-mt-24 {{ $style['border'] }}"
            >
                <!-- MAIN TABLE ACCORDION HEADER (CLICKABLE DROPDOWN) -->
                <div 
                    @click="toggleSection('{{ $tableName }}')"
                    class="px-5 py-3.5 flex items-center justify-between cursor-pointer transition select-none border-b {{ $style['header_bg'] }}"
                >
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm font-extrabold border shadow-2xs {{ $style['badge'] }}">
                            <span class="size-2.5 rounded-full {{ $style['dot'] }} animate-pulse"></span>
                            <span>{{ $tableName }}</span>
                        </span>
                        <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                            {{ count($tableTools) }} {{ count($tableTools) === 1 ? 'Tool Available' : 'Tools Available' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                        <span class="text-[11px] font-medium hidden sm:inline" x-text="openSections['{{ $tableName }}'] ? 'Click to collapse' : 'Click to expand'"></span>
                        <flux:icon.chevron-down 
                            class="size-5 transition-transform duration-200" 
                            x-bind:class="{ 'rotate-180': openSections['{{ $tableName }}'] }" 
                        />
                    </div>
                </div>

                <!-- SUB-TOOLS CONTENT (COLLAPSIBLE) -->
                <div 
                    x-show="openSections['{{ $tableName }}']" 
                    x-cloak 
                    x-collapse
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/60 text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                                <tr>
                                    <th class="px-5 py-2.5 w-14">#</th>
                                    <th class="px-5 py-2.5 w-64">Target Field / Sub Tool</th>
                                    <th class="px-5 py-2.5">Transformation Summary</th>
                                    <th class="px-5 py-2.5 text-center w-28">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-800">
                                @foreach($tableTools as $subIndex => $tool)
                                    @php
                                        $globalIndex++;
                                    @endphp
                                    <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                                        <!-- Index -->
                                        <td class="px-5 py-3.5 font-semibold text-zinc-400 dark:text-zinc-500">
                                            {{ $globalIndex }}
                                        </td>

                                        <!-- Target Field Title -->
                                        <td class="px-5 py-3.5">
                                            <code class="font-mono px-2 py-0.5 rounded text-xs font-bold border block w-fit max-w-xs truncate {{ $style['badge'] }}" title="{{ $tool['title'] }}">
                                                {{ $tool['title'] }}
                                            </code>
                                        </td>

                                        <!-- Description -->
                                        <td class="px-5 py-3.5 text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                            {{ $tool['description'] }}
                                        </td>

                                        <!-- Action Button: GO -->
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <a 
                                                href="{{ $tool['route'] }}"
                                                wire:navigate
                                                class="inline-flex items-center gap-1 px-4 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 border transition shadow-2xs cursor-pointer tracking-wider uppercase hover:shadow-xs active:scale-95 {{ $style['btn'] }}"
                                            >
                                                <span>GO</span>
                                                <flux:icon.arrow-right class="size-3" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
