<?php

namespace App\Livewire\Concerns;

use App\Models\Publication;
use MongoDB\BSON\Regex;

trait HandlesDuplicateChecks
{
    /**
     * Controls the visibility of the Duplicate Popup Modal.
     */
    public bool $showDuplicateModal = false;

    /**
     * Single Unified Method: Checks MongoDB for duplicate title,
     * opens modal, and clears title input if duplicate found.
     */
    public function checkPublicationTitleDuplicate(?string $val = null, ?string $excludeId = null): bool
    {
        if ($val !== null) {
            $this->title = $val;
        }

        $title = trim($this->title ?? '');

        // If title is empty or less than 5 characters, do not query
        if ($title === '' || mb_strlen($title) < 5) {
            $this->showDuplicateModal = false;

            return false;
        }

        // Case-insensitive exact full-string regex match in MongoDB
        $escaped = preg_quote($title, '/');
        $regex = new Regex('^'.$escaped.'$', 'i');

        $query = Publication::where('title', 'regex', $regex);

        if ($excludeId) {
            $query->where('_id', '!=', $excludeId);
        }

        $exists = $query->exists();

        if ($exists) {
            $this->showDuplicateModal = true;
            $this->title = '';
            $this->resetValidation('title');
        } else {
            $this->showDuplicateModal = false;
            $this->resetValidation('title');
        }

        return $exists;
    }

    /**
     * Closes the duplicate popup modal and resets the title input.
     */
    public function closeDuplicateModal(): void
    {
        $this->showDuplicateModal = false;
        $this->title = '';
        $this->resetValidation('title');
    }

    /**
     * Clears validation errors after 5 seconds to restore normal input borders.
     */
    public function clearFieldValidation(?string $field = null): void
    {
        if ($field) {
            $this->resetValidation($field);
        } else {
            $this->resetValidation();
        }
    }
}
