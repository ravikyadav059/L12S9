<?php

namespace App\Models;

use App\Helpers\IssnHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;

class ReviewerJournal extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'reviewer_journal';

    protected $table = 'reviewer_journal';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'journal_photo',
        'user_id',
        'journal_title',
        'slug',
        'serial_number',
        'journal_short_name',
        'e_issn',
        'p_issn',
        'journal_webiste_url',
        'first_publication_year',
        'issn_assignee_country',
        'journal_impact_factor',
        'impact_factor_assignee_agency',
        'languages',
        'issue_frequency',
        'organization_name',
        'journal_subjects',
        'contact_name',
        'contact_email',
        'password',
        'country_code',
        'mobile',
        'journal_description',
        'address',
        'country',
        'state',
        'city',
        'zipcode',
        'JournalOpenAccess',
        'OpenAccessLicense',
        'COPEguidelines',
        'restrictions',
        'followpeer',
        'plagiarism',
        'publicationPlatform',
        'feesAssociatedWithPublishing',
        'preservation',
        'DOI',
        'doi',
        'ScopusProfile',
        'GoogleScholar',
        'WebofScience',
        'SSRN',
        'Academia',
        'EditorialReview',
        'PeerReview',
        'AnonymousPeerReview',
        'DoubleAnonymousPeerReview',
        'PostPublicationPeerReview',
        'OpenPeerReview',
        'other',
        'otherPeer',
        'status',
        'registered_co_author',
        'unregistered_co_author',
        'credits',
        'cradits',
        'is_credited',
        'migration_status',
        'migration_message',
        'tenant_id',
        'domain',
        'jms_server_url',
        'conference_charges',
        'conference_allowed',
        'journal_id',
        'external_journal_id',

        // Verification Status & Metadata
        'is_verified',
        'verification_status',
        'verification_source',
        'verified_at',
        'verification_details',

        // Indexing & Directory URLs / Checkboxes
        'ABCD',
        'ABCD_checkbox',
        'CAS',
        'CAS_checkbox',
        'Crossref',
        'Crossref_checkbox',
        'DOAJ',
        'DOAJ_checkbox',
        'EBSCO',
        'EBSCO_checkbox',
        'Elsevier',
        'Elsevier_checkbox',
        'Frontiers',
        'Frontiers_checkbox',
        'Google_Scholar',
        'Google_Scholar_checkbox',
        'IJIFACTOR',
        'IJIFACTOR_checkbox',
        'ISI',
        'ISI_checkbox',
        'J-Gate',
        'J-Gate_checkbox',
        'Mendeley',
        'Mendeley_checkbox',
        'ProQuest',
        'ProQuest_checkbox',
        'PubMed',
        'PubMed_checkbox',
        'Publon',
        'Publon_checkbox',
        'Publons',
        'Publons_checkbox',
        'Research_Gate',
        'Research_Gate_checkbox',
        'ResearchGate',
        'ResearchGate_checkbox',
        'Resurchify',
        'Resurchify_checkbox',
        'SJR',
        'SJR_checkbox',
        'Scholastica',
        'Scholastica_checkbox',
        'Science_Direct',
        'Science_Direct_checkbox',
        'Scopus',
        'Scopus_checkbox',
        'UGC_CARE',
        'UGC_CARE_checkbox',
        'Ulrichsweb',
        'Ulrichsweb_checkbox',
        'WoS',
        'WoS_checkbox',
        'AMS',
        'AMS_checkbox',
        'CNKI_Scholar',
        'CNKI_Scholar_checkbox',
        'CiteSeerX',
        'CiteSeerX_checkbox',
        'Citefactor',
        'Citefactor_checkbox',
        'Connect_Journals',
        'Connect_Journals_checkbox',
        'Cosmos',
        'Cosmos_checkbox',
        'Current_Index_to_Statistics_(CIS)',
        'Current_Index_to_Statistics_(CIS)_checkbox',
        'DBLP',
        'DBLP_checkbox',
        'DOI_Foundation',
        'DOI_Foundation_checkbox',
        'DocStoc',
        'DocStoc_checkbox',
        'EBSCOhost',
        'EBSCOhost_checkbox',
        'EI_Compendex',
        'EI_Compendex_checkbox',
        'Electronic_Journals_Library_(EZB)',
        'Electronic_Journals_Library_(EZB)_checkbox',
        'Elektronische_Zeitschriftenbibliothek',
        'Elektronische_Zeitschriftenbibliothek_checkbox',
        'Emerging_Sources_Citation_Index_(ESCI)',
        'Emerging_Sources_Citation_Index_(ESCI)_checkbox',
        'Eurasian_Scientific_Journal_Index',
        'Eurasian_Scientific_Journal_Index_checkbox',
        'Excellence_in_Research_for_Australia',
        'Excellence_in_Research_for_Australia_checkbox',
        'FatCat',
        'FatCat_checkbox',
        'Fidmath_(Fachinformationsdienst_Mathematik)',
        'Fidmath_(Fachinformationsdienst_Mathematik)_checkbox',
        'Genamics_JournalSeek',
        'Genamics_JournalSeek_checkbox',
        'INSPEC',
        'INSPEC_checkbox',
        'ISSN_Portal',
        'ISSN_Portal_checkbox',
        'ISSUU',
        'ISSUU_checkbox',
        'Index_Copernicus',
        'Index_Copernicus_checkbox',
        'Index_Medicus_SEAR',
        'Index_Medicus_SEAR_checkbox',
        'Indian_Citation_Index_(ICI)',
        'Indian_Citation_Index_(ICI)_checkbox',
        'Indian_Science_Abstracts_(ISA)',
        'Indian_Science_Abstracts_(ISA)_checkbox',
        'Japan_Science_and_Technology_Agency_(JST)',
        'Japan_Science_and_Technology_Agency_(JST)_checkbox',
        'Journal_ITOCs',
        'Journal_ITOCs_checkbox',
        'Kriterier',
        'Kriterier_checkbox',
        'LocumUSA',
        'LocumUSA_checkbox',
        'Microsoft_Academic',
        'Microsoft_Academic_checkbox',
        'Mostwiedzy',
        'Mostwiedzy_checkbox',
        'National_Library_of_Medicine',
        'National_Library_of_Medicine_checkbox',
        'National_Library_of_Medicine_(NLM)',
        'National_Library_of_Medicine_(NLM)_checkbox',
        'National_Library_of_Australia_(NLA)',
        'National_Library_of_Australia_(NLA)_checkbox',
        'OALib_Journal',
        'OALib_Journal_checkbox',
        'OCLC',
        'OCLC_checkbox',
        'ORES',
        'ORES_checkbox',
        'ResearchBib',
        'ResearchBib_checkbox',
        'ResearcherID',
        'ResearcherID_checkbox',
        'ResearcherID_Thomson_Reuters',
        'ResearcherID_Thomson_Reuters_checkbox',
        'Researcher_Life',
        'Researcher_Life_checkbox',
        'Samamsystem',
        'Samamsystem_checkbox',
        'ScienceGate',
        'ScienceGate_checkbox',
        'Scilit',
        'Scilit_checkbox',
        'Scinapse',
        'Scinapse_checkbox',
        'Scribd',
        'Scribd_checkbox',
        'Semantic_Scholar',
        'Semantic_Scholar_checkbox',
        'SherpaRomeo',
        'SherpaRomeo_checkbox',
        'Social_Science_Research_Network_(SSRN)',
        'Social_Science_Research_Network_(SSRN)_checkbox',
        'The_University_of_Utah_Data_Repositories',
        'The_University_of_Utah_Data_Repositories_checkbox',
        'Thomson_Reuters',
        'Thomson_Reuters_checkbox',
        'WTI_Frankfurt_eG',
        'WTI_Frankfurt_eG_checkbox',
        'Wikidata',
        'Wikidata_checkbox',
        'WorldCat',
        'WorldCat_checkbox',
        'World_Journal_Clout_Index_(WJCI)',
        'World_Journal_Clout_Index_(WJCI)_checkbox',
        'Zeitschriften_Datenbank',
        'Zeitschriften_Datenbank_checkbox',
        'Zeitschriften_Datenbank_(ZDB)',
        'Zeitschriften_Datenbank_(ZDB)_checkbox',
        'Zentralblatt_MATH',
        'Zentralblatt_MATH_checkbox',
        'Zurich_Open_Repository_and_Archive',
        'Zurich_Open_Repository_and_Archive_checkbox',
        'iFocus',
        'iFocus_checkbox',
        'iJournals',
        'iJournals_checkbox',
        'ACM_Digital_Library',
        'ACM_Digital_Library_checkbox',
        'ADVANCED_SCIENCES_INDEX',
        'ADVANCED_SCIENCES_INDEX_checkbox',
        'AcademicKeys',
        'AcademicKeys_checkbox',
        'Amidalla',
        'Amidalla_checkbox',
        'Astrophysics_Data_System_(ADS)',
        'Astrophysics_Data_System_(ADS)_checkbox',
        'Atypon',
        'Atypon_checkbox',
        'BASE',
        'BASE_checkbox',
        'BibSonomy',
        'BibSonomy_checkbox',
        'Bing',
        'Bing_checkbox',
        'CABI',
        'CABI_checkbox',
        'CHORUS',
        'CHORUS_checkbox',
        'CIS',
        'CIS_checkbox',
        'COPE_(Committee_on_Publication_Ethics)',
        'COPE_(Committee_on_Publication_Ethics)_checkbox',
        'COUNTER',
        'COUNTER_checkbox',
        'Cabell\'s_Directory',
        'Cabell\'s_Directory_checkbox',
        'Clarivate_Analytics',
        'Clarivate_Analytics_checkbox',
        'DAIJ',
        'DAIJ_checkbox',
        'DRJI',
        'DRJI_checkbox',
        'Dimensions',
        'Dimensions_checkbox',
        'ESCI',
        'ESCI_checkbox',
        'ESJI',
        'ESJI_checkbox',
        'EZB',
        'EZB_checkbox',
        'Embase',
        'Embase_checkbox',
        'Emerald_Publishing',
        'Emerald_Publishing_checkbox',
        'Endnote',
        'Endnote_checkbox',
        'EuroPub',
        'EuroPub_checkbox',
        'Fidmath',
        'Fidmath_checkbox',
        'GFMER',
        'GFMER_checkbox',
        'GeoRef',
        'GeoRef_checkbox',
        'GetCited',
        'GetCited_checkbox',
        'GitHub',
        'GitHub_checkbox',
        'ICI',
        'ICI_checkbox',
        'IIFS',
        'IIFS_checkbox',
        'IP_Indexing_Portal',
        'IP_Indexing_Portal_checkbox',
        'ISEDN',
        'ISEDN_checkbox',
        'Informatics',
        'Informatics_checkbox',
        'Internet_Archive',
        'Internet_Archive_checkbox',
        'JOURNAL_FACTOR',
        'JOURNAL_FACTOR_checkbox',
        'JST',
        'JST_checkbox',
        'Jour_Informatics',
        'Jour_Informatics_checkbox',
        'MEDLINE',
        'MEDLINE_checkbox',
        'MIAR',
        'MIAR_checkbox',
        'Mirabel',
        'Mirabel_checkbox',
        'NASA',
        'NASA_checkbox',
        'New_Jour',
        'New_Jour_checkbox',
        'OAJI',
        'OAJI_checkbox',
        'ORCID',
        'ORCID_checkbox',
        'OpenAIRE',
        'OpenAIRE_checkbox',
        'Peeref',
        'Peeref_checkbox',
        'Portico',
        'Portico_checkbox',
        'RJIF',
        'RJIF_checkbox',
        'ROAD',
        'ROAD_checkbox',
        'RePEc',
        'RePEc_checkbox',
        'RefSeek',
        'RefSeek_checkbox',
        'ResearchBible',
        'ResearchBible_checkbox',
        'Root_Indexing',
        'Root_Indexing_checkbox',
        'SCI',
        'SCI_checkbox',
        'SJIF',
        'SJIF_checkbox',
        'SSRN_checkbox',
        'Scholars_Research_Library',
        'Scholars_Research_Library_checkbox',
        'ScienceCentral',
        'ScienceCentral_checkbox',
        'ScienceOpen',
        'ScienceOpen_checkbox',
        'Science_Citation_Index_Expanded_(SCIE)',
        'Science_Citation_Index_Expanded_(SCIE)_checkbox',
        'ScientificCommons',
        'ScientificCommons_checkbox',
        'Scientific_Indexing_Services_(SIS)',
        'Scientific_Indexing_Services_(SIS)_checkbox',
        'Scirus',
        'Scirus_checkbox',
        'Scite',
        'Scite_checkbox',
        'SlideShare',
        'SlideShare_checkbox',
        'Sudoc',
        'Sudoc_checkbox',
        'UTeM',
        'UTeM_checkbox',
        'WJCI',
        'WJCI_checkbox',
        'WikiCFP',
        'WikiCFP_checkbox',
        'Zenodo',
        'Zenodo_checkbox',
        'arXiv',
        'arXiv_checkbox',
        'i-scholar',
        'i-scholar_checkbox',
        'ourGlocal',
        'ourGlocal_checkbox',
        'Springer_Nature',
        'Springer_Nature_checkbox',
        'Wikipedia',
        'Wikipedia_checkbox',
        'Wiley',
        'Wiley_checkbox',
        'Zb_MATH',
        'Zb_MATH_checkbox',
        'Sage_Journals',
        'Sage_Journals_checkbox',
        'Scope_Database',
        'Scope_Database_checkbox',
        'Taylor_&_Francis',
        'Taylor_&_Francis_checkbox',
        'Wolters_Kluwer',
        'Wolters_Kluwer_checkbox',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'serial_number' => 'integer',
            'first_publication_year' => 'integer',
            'is_credited' => 'boolean',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (ReviewerJournal $journal): void {
            if (empty($journal->serial_number)) {
                $maxSerial = (int) static::max('serial_number');
                $journal->serial_number = $maxSerial + 1;
            }

            if (empty($journal->slug)) {
                $base = Str::slug($journal->journal_title ?: $journal->journal_short_name ?: 'journal');
                $journal->slug = $base !== '' ? "{$base}-{$journal->serial_number}" : (string) $journal->serial_number;
            }

            if ($journal->status === null || $journal->status === '') {
                $journal->status = 0;
            } else {
                $journal->status = (int) $journal->status;
            }
        });

        static::saved(function (): void {
            static::forgetCaches();
        });

        static::deleted(function (): void {
            static::forgetCaches();
        });
    }

    /**
     * Clear cached journal counts and filter lists from Redis/Cache.
     */
    public static function forgetCaches(): void
    {
        Cache::forget('total_active_journals_count');
        Cache::forget('journal_disciplines_list');
        Cache::forget('journal_indexers_list');
        Cache::put('j_search_version', time());
        IndexingAgency::forgetCached();
    }

    /**
     * Get the country name resolved from the Country model.
     */
    public function countryName(): ?string
    {
        $val = $this->country ?: $this->issn_assignee_country;
        if (empty($val)) {
            return null;
        }

        $countries = Cache::remember('countries_all_collection', 86400, fn () => Country::all());

        return $countries->firstWhere('id', (int) $val)?->name ?? (string) $val;
    }

    /**
     * Format an ISSN string to standard XXXX-XXXX format.
     */
    public static function formatIssn(?string $issn): string
    {
        return IssnHelper::format($issn);
    }

    /**
     * Check if an ISSN string has a valid XXXX-XXXX format.
     */
    public static function isValidIssn(?string $issn): bool
    {
        return IssnHelper::isValid($issn);
    }

    /**
     * Search existing journals by title for autocomplete / auto-fill.
     * Searches both active and inactive journals (excludes only deleted).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function searchTitles(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $searchVer = Cache::get('j_search_version', 1);
        $cacheKey = 'j_search_v3_'.$searchVer.'_'.md5(strtolower($query).'_'.$limit);

        return Cache::remember($cacheKey, 300, function () use ($query, $limit) {
            $fields = [
                '_id',
                'journal_title',
                'journal_short_name',
                'user_id',
                'status',
                'e_issn',
                'p_issn',
                'journal_webiste_url',
                'first_publication_year',
                'issn_assignee_country',
                'journal_impact_factor',
                'impact_factor_assignee_agency',
                'organization_name',
                'issue_frequency',
                'languages',
                'journal_subjects',
            ];

            // Exclude only deleted journals (status 3), including active (1) and inactive (0)
            $results = static::where(function ($q) {
                $q->where('status', '!=', 3)->where('status', '!=', '3');
            })
                ->where('journal_title', 'like', "{$query}%")
                ->limit($limit)
                ->get($fields);

            // Also check if title starts with quotation mark (e.g. "'Chintan'...")
            if ($results->count() < $limit) {
                $quoted = static::where(function ($q) {
                    $q->where('status', '!=', 3)->where('status', '!=', '3');
                })
                    ->where('journal_title', 'like', "'{$query}%")
                    ->limit($limit - $results->count())
                    ->get($fields);
                $results = $results->merge($quoted);
            }

            // If fewer than $limit results, supplement with contains search
            if ($results->count() < $limit) {
                $remaining = $limit - $results->count();
                $excludeIds = $results->pluck('_id')->toArray();

                $more = static::where(function ($q) {
                    $q->where('status', '!=', 3)->where('status', '!=', '3');
                })
                    ->where('journal_title', 'like', "%{$query}%")
                    ->whereNotIn('_id', $excludeIds)
                    ->limit($remaining)
                    ->get($fields);

                $results = $results->merge($more);
            }

            return $results->map(function ($j) {
                $hasUser = ! empty($j->user_id);
                $statusInt = (int) ($j->status ?? 1);

                return [
                    'id' => (string) $j->_id,
                    'journal_title' => (string) ($j->journal_title ?? ''),
                    'journal_short_name' => (string) ($j->journal_short_name ?? ''),
                    'has_user' => $hasUser,
                    'user_id' => $hasUser ? (string) $j->user_id : null,
                    'status' => $statusInt,
                    'status_label' => $statusInt === 1 ? 'Active' : 'Inactive',
                    'e_issn' => (string) ($j->e_issn ?? ''),
                    'p_issn' => (string) ($j->p_issn ?? ''),
                    'journal_webiste_url' => (string) ($j->journal_webiste_url ?? ''),
                    'first_publication_year' => $j->first_publication_year ? (int) $j->first_publication_year : null,
                    'issn_assignee_country' => (string) ($j->issn_assignee_country ?? ''),
                    'journal_impact_factor' => (string) ($j->journal_impact_factor ?? ''),
                    'impact_factor_assignee_agency' => (string) ($j->impact_factor_assignee_agency ?? ''),
                    'organization_name' => (string) ($j->organization_name ?? ''),
                    'issue_frequency' => (string) ($j->issue_frequency ?? ''),
                    'languages' => is_array($j->languages) ? array_values(array_filter($j->languages)) : (! empty($j->languages) ? [(string) $j->languages] : []),
                    'journal_subjects' => is_array($j->journal_subjects) ? array_values(array_filter($j->journal_subjects)) : (! empty($j->journal_subjects) ? [(string) $j->journal_subjects] : []),
                ];
            })
                ->values()
                ->toArray();
        });
    }

    /**
     * Get list of publication years from current year down to 1800 formatted for searchable select.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function getPublicationYears(): array
    {
        $currentYear = (int) date('Y');
        $years = [];
        for ($y = $currentYear; $y >= 1800; $y--) {
            $years[] = [
                'id' => $y,
                'name' => (string) $y,
            ];
        }

        return $years;
    }

    /**
     * Get default languages for journal acceptance.
     *
     * @return array<int, string>
     */
    public static function getDefaultLanguages(): array
    {
        return [
            'Hindi',
            'English',
        ];
    }

    /**
     * Get default disciplines/subjects for journals.
     *
     * @return array<int, string>
     */
    public static function getDefaultDisciplines(): array
    {
        return [
            'Socila',
            'Developing',
        ];
    }
}
