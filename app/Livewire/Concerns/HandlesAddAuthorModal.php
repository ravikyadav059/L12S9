<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use MongoDB\BSON\Regex;

trait HandlesAddAuthorModal
{
    /**
     * Controls the visibility of the Add New Author Popup Modal.
     */
    public bool $showAddAuthorModal = false;

    /**
     * Input fields for adding a new author.
     */
    public string $newAuthorFirstName = '';

    public string $newAuthorLastName = '';

    public string $newAuthorEmail = '';

    /**
     * The active author array index in the parent form being edited.
     */
    public ?int $activeAuthorIndex = null;

    /**
     * Error message displayed if the email belongs to an existing author.
     */
    public ?string $existingAuthorErrorMessage = null;

    /**
     * Opens the Add New Author popup modal with optional active index and prefilled search text.
     */
    public function openAddAuthorModal(?int $index = null, ?string $prefillName = null): void
    {
        $this->activeAuthorIndex = $index;
        $this->existingAuthorErrorMessage = null;
        $this->newAuthorEmail = '';

        $name = $prefillName !== null ? trim($prefillName) : (
            ($index !== null && isset($this->authors[$index]['name'])) ? trim($this->authors[$index]['name']) : ''
        );

        if (! empty($name)) {
            $parts = array_values(array_filter(explode(' ', $name)));
            if (count($parts) >= 2) {
                $this->newAuthorFirstName = $parts[0];
                $this->newAuthorLastName = implode(' ', array_slice($parts, 1));
            } elseif (count($parts) === 1) {
                $this->newAuthorFirstName = $parts[0];
                $this->newAuthorLastName = '';
            } else {
                $this->newAuthorFirstName = '';
                $this->newAuthorLastName = '';
            }
        } else {
            $this->newAuthorFirstName = '';
            $this->newAuthorLastName = '';
        }

        if (property_exists($this, 'showAuthorDropdown') && is_array($this->showAuthorDropdown)) {
            if ($index !== null) {
                $this->showAuthorDropdown[$index] = false;
            }
        }

        $this->showAddAuthorModal = true;
        $this->resetValidation([
            'newAuthorFirstName',
            'newAuthorLastName',
            'newAuthorEmail',
        ]);
    }

    /**
     * Closes the Add New Author popup modal and resets state.
     */
    public function closeAddAuthorModal(): void
    {
        $this->showAddAuthorModal = false;
        $this->newAuthorFirstName = '';
        $this->newAuthorLastName = '';
        $this->newAuthorEmail = '';
        $this->activeAuthorIndex = null;
        $this->existingAuthorErrorMessage = null;

        $this->resetValidation([
            'newAuthorFirstName',
            'newAuthorLastName',
            'newAuthorEmail',
        ]);
    }

    /**
     * Validates, checks for existing email, and saves the new author.
     */
    public function saveNewAuthor(): void
    {
        $this->existingAuthorErrorMessage = null;

        $this->validate([
            'newAuthorFirstName' => 'required|string|max:100',
            'newAuthorLastName' => 'required|string|max:100',
            'newAuthorEmail' => 'required|string|email|max:255',
        ], [
            'newAuthorFirstName.required' => 'First name is required.',
            'newAuthorLastName.required' => 'Last name is required.',
            'newAuthorEmail.required' => 'Email address is required.',
            'newAuthorEmail.email' => 'Please provide a valid email address.',
        ]);

        $firstName = trim($this->newAuthorFirstName);
        $lastName = trim($this->newAuthorLastName);
        $email = strtolower(trim($this->newAuthorEmail));

        // Check if email already exists in users collection
        $escapedEmail = preg_quote($email, '/');
        $existingUser = User::where('email', 'regex', new Regex('^'.$escapedEmail.'$', 'i'))->first();

        if ($existingUser) {
            $existingName = trim(($existingUser->first_name ?? '').' '.($existingUser->last_name ?? ''));
            if (empty($existingName)) {
                $existingName = $existingUser->fullname ?? 'Unknown Author';
            }

            $this->existingAuthorErrorMessage = "This email is already registered with author '{$existingName}'.";
            $this->addError('newAuthorEmail', $this->existingAuthorErrorMessage);

            return;
        }

        // Create new user record (sequence_number, unique_id, and slug generated automatically by User model)
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'fullname' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'type' => 0,
            'status' => 1,
        ]);

        $fullName = trim("{$firstName} {$lastName}");

        // Auto-fill into parent form authors list if index is set
        if ($this->activeAuthorIndex !== null && property_exists($this, 'authors') && isset($this->authors[$this->activeAuthorIndex])) {
            $this->authors[$this->activeAuthorIndex]['name'] = $fullName;
            $this->authors[$this->activeAuthorIndex]['email'] = $email;
            $this->authors[$this->activeAuthorIndex]['affiliation'] = '';

            if (property_exists($this, 'isAuthorSelected') && is_array($this->isAuthorSelected)) {
                $this->isAuthorSelected[$this->activeAuthorIndex] = true;
            }
            if (property_exists($this, 'authorSearchResults') && is_array($this->authorSearchResults)) {
                $this->authorSearchResults[$this->activeAuthorIndex] = [];
            }
            if (property_exists($this, 'authorSearchNoResults') && is_array($this->authorSearchNoResults)) {
                $this->authorSearchNoResults[$this->activeAuthorIndex] = false;
            }
            if (property_exists($this, 'showAuthorDropdown') && is_array($this->showAuthorDropdown)) {
                $this->showAuthorDropdown[$this->activeAuthorIndex] = false;
            }

            $this->resetValidation("authors.{$this->activeAuthorIndex}.name");
            $this->resetValidation("authors.{$this->activeAuthorIndex}.email");
        }

        session()->flash('author_added_success', "Author '{$fullName}' added successfully!");

        $this->closeAddAuthorModal();
    }
}
