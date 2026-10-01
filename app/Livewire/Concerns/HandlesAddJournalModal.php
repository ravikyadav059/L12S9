<?php

namespace App\Livewire\Concerns;

use App\Models\ReviewerJournal;
use App\Rules\ValidIssn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use MongoDB\BSON\Regex;

trait HandlesAddJournalModal
{
    /**
     * Controls the visibility of the Add New Journal Popup Modal.
     */
    public bool $showAddJournalModal = false;

    /**
     * Input fields for adding a new journal.
     */
    public string $newJournalTitle = '';

    public string $newJournalEIssn = '';

    public string $newJournalPIssn = '';

    /**
     * Opens the Add New Journal popup modal with optional prefilled title.
     */
    public function openAddJournalModal(?string $prefillTitle = null): void
    {
        $this->newJournalTitle = $prefillTitle !== null ? trim($prefillTitle) : (isset($this->journalName) ? trim($this->journalName) : '');
        $this->newJournalEIssn = '';
        $this->newJournalPIssn = '';

        if (property_exists($this, 'showJournalDropdown')) {
            $this->showJournalDropdown = false;
        }

        $this->showAddJournalModal = true;
        $this->resetValidation([
            'newJournalTitle',
            'newJournalEIssn',
            'newJournalPIssn',
            'newJournalIssnRequired',
        ]);
    }

    /**
     * Closes the Add New Journal popup modal and clears temporary inputs.
     */
    public function closeAddJournalModal(): void
    {
        $this->showAddJournalModal = false;

        if (property_exists($this, 'isJournalVerified') && ! $this->isJournalVerified) {
            if (property_exists($this, 'journalName')) {
                $this->journalName = '';
            }
            if (property_exists($this, 'selectedJournalId')) {
                $this->selectedJournalId = null;
            }
            if (property_exists($this, 'journalSearchResults')) {
                $this->journalSearchResults = [];
            }
        }

        $this->newJournalTitle = '';
        $this->newJournalEIssn = '';
        $this->newJournalPIssn = '';

        if (property_exists($this, 'showJournalDropdown')) {
            $this->showJournalDropdown = false;
        }

        $this->resetValidation([
            'newJournalTitle',
            'newJournalEIssn',
            'newJournalPIssn',
            'newJournalIssnRequired',
        ]);
    }

    /**
     * Validates and saves a new journal into MongoDB ReviewerJournal collection.
     */
    public function saveNewJournal(): ?ReviewerJournal
    {
        $this->resetValidation([
            'newJournalTitle',
            'newJournalEIssn',
            'newJournalPIssn',
            'newJournalIssnRequired',
        ]);

        $this->validate([
            'newJournalTitle' => 'required|string|min:2|max:255',
            'newJournalEIssn' => ['nullable', 'string', new ValidIssn],
            'newJournalPIssn' => ['nullable', 'string', new ValidIssn],
        ], [
            'newJournalTitle.required' => 'Journal Name is required.',
            'newJournalTitle.min' => 'Journal Name must be at least 2 characters.',
        ]);

        $titleClean = trim($this->newJournalTitle);
        $eClean = strtoupper(trim($this->newJournalEIssn));
        $pClean = strtoupper(trim($this->newJournalPIssn));

        if ($eClean === '' && $pClean === '') {
            $this->addError('newJournalIssnRequired', 'At least one ISSN (e-ISSN or p-ISSN) is required');

            return null;
        }

        if ($eClean !== '' && $pClean !== '' && $eClean === $pClean) {
            $this->addError('newJournalPIssn', 'P-ISSN must be different from E-ISSN.');

            return null;
        }

        // 1. Uniqueness check for Journal Title (case-insensitive)
        $escapedTitle = preg_quote($titleClean, '/');
        $titleRegex = new Regex('^'.$escapedTitle.'$', 'i');
        $existingTitle = ReviewerJournal::where('journal_title', 'regex', $titleRegex)
            ->where('status', '!=', 3)
            ->where('status', '!=', '3')
            ->exists();

        if ($existingTitle) {
            $this->addError('newJournalTitle', 'This journal name already exists in the database.');

            return null;
        }

        // 2. Uniqueness check for e-ISSN
        if ($eClean !== '') {
            $escapedEIssn = preg_quote($eClean, '/');
            $eIssnRegex = new Regex('^'.$escapedEIssn.'$', 'i');
            $existingEIssn = ReviewerJournal::where(function ($q) use ($eIssnRegex) {
                $q->where('e_issn', 'regex', $eIssnRegex)
                    ->orWhere('p_issn', 'regex', $eIssnRegex);
            })
                ->where('status', '!=', 3)
                ->where('status', '!=', '3')
                ->exists();

            if ($existingEIssn) {
                $this->addError('newJournalEIssn', 'This e-ISSN is already registered to an existing journal.');

                return null;
            }
        }

        // 3. Uniqueness check for p-ISSN
        if ($pClean !== '') {
            $escapedPIssn = preg_quote($pClean, '/');
            $pIssnRegex = new Regex('^'.$escapedPIssn.'$', 'i');
            $existingPIssn = ReviewerJournal::where(function ($q) use ($pIssnRegex) {
                $q->where('p_issn', 'regex', $pIssnRegex)
                    ->orWhere('e_issn', 'regex', $pIssnRegex);
            })
                ->where('status', '!=', 3)
                ->where('status', '!=', '3')
                ->exists();

            if ($existingPIssn) {
                $this->addError('newJournalPIssn', 'This p-ISSN is already registered to an existing journal.');

                return null;
            }
        }

        // Calculate next auto-incrementing serial_number
        $maxSerial = (int) ReviewerJournal::max('serial_number');
        if ($maxSerial <= 0) {
            $lastJournal = ReviewerJournal::whereNotNull('serial_number')->orderBy('serial_number', 'desc')->first();
            $maxSerial = $lastJournal && is_numeric($lastJournal->serial_number) ? (int) $lastJournal->serial_number : 0;
        }
        $nextSerialNumber = $maxSerial + 1;

        $baseSlug = Str::slug($titleClean);
        $journalSlug = $baseSlug !== '' ? ($baseSlug.'-'.$nextSerialNumber) : (string) $nextSerialNumber;

        $journal = ReviewerJournal::create([
            'journal_title' => $titleClean,
            'serial_number' => $nextSerialNumber,
            'slug' => $journalSlug,
            'e_issn' => $eClean ?: null,
            'p_issn' => $pClean ?: null,
            'status' => 1,
            'user_id' => Auth::id(),
        ]);

        // Auto-populate calling component's fields if present
        if (property_exists($this, 'selectedJournalId')) {
            $this->selectedJournalId = (string) $journal->_id;
        }
        if (property_exists($this, 'journalName')) {
            $this->journalName = $journal->journal_title;
        }
        if (property_exists($this, 'eIssn')) {
            $this->eIssn = $eClean;
        }
        if (property_exists($this, 'pIssn')) {
            $this->pIssn = $pClean;
        }

        if (property_exists($this, 'issnType')) {
            if ($eClean !== '' && $pClean !== '') {
                $this->issnType = 'both';
                if (property_exists($this, 'issn')) {
                    $this->issn = $eClean;
                }
            } elseif ($eClean !== '') {
                $this->issnType = 'e_issn';
                if (property_exists($this, 'issn')) {
                    $this->issn = $eClean;
                }
            } elseif ($pClean !== '') {
                $this->issnType = 'p_issn';
                if (property_exists($this, 'issn')) {
                    $this->issn = $pClean;
                }
            } else {
                if (property_exists($this, 'issn')) {
                    $this->issn = '';
                }
            }
        }

        if (property_exists($this, 'isJournalVerified')) {
            $this->isJournalVerified = true;
        }
        if (property_exists($this, 'showJournalDropdown')) {
            $this->showJournalDropdown = false;
        }
        if (property_exists($this, 'journalSearchResults')) {
            $this->journalSearchResults = [];
        }

        $this->showAddJournalModal = false;
        $this->resetValidation();

        // Allow component-specific hook
        if (method_exists($this, 'afterJournalSaved')) {
            $this->afterJournalSaved($journal);
        }

        // Global Livewire event for other listeners
        $this->dispatch('journal-created', id: (string) $journal->_id, title: $journal->journal_title, e_issn: $journal->e_issn, p_issn: $journal->p_issn);

        session()->flash('journal_added_success', 'Journal added successfully.');

        return $journal;
    }
}
