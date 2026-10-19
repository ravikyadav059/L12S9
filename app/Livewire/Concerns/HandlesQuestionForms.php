<?php

namespace App\Livewire\Concerns;

use App\Models\Question;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use MongoDB\BSON\Regex;

/**
 * Trait HandlesQuestionForms
 *
 * Centralized trait providing question creation and editing form logic:
 * - Ask Question modal state, validation & creation
 * - Edit Question modal state, pre-population & updates
 * - Title uniqueness verification
 * - Skills & tags autocomplete search
 *
 * Used in:
 * - resources/views/livewire/question/index.blade.php (Questions Feed)
 * - resources/views/livewire/question/show.blade.php (Question Details)
 */
trait HandlesQuestionForms
{
    // ==========================================
    // 1. ASK QUESTION FORM STATE
    // ==========================================
    #[Validate('required|min:5|max:255')]
    public string $newTitle = '';

    #[Validate('required|min:10')]
    public string $newDescription = '';

    public string $newTags = '';

    #[Validate('nullable|image|max:5120')]
    public $newThumbnail;

    public bool $showAskModal = false;

    // ==========================================
    // 2. EDIT QUESTION FORM STATE
    // ==========================================
    public ?string $editingQuestionId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editTags = '';

    #[Validate('nullable|image|max:5120')]
    public $editThumbnail;

    public ?string $existingThumbnail = null;

    public bool $showEditModal = false;

    /**
     * Check if a question title is available (unique).
     */
    public function checkTitleAvailable(string $title, ?string $excludeId = null): bool
    {
        return Question::isTitleUnique($title, $excludeId);
    }

    /**
     * Open the Ask Question modal with clean state.
     */
    public function openAskModal(): void
    {
        $this->resetValidation();
        if (method_exists($this, 'resetErrorBag')) {
            $this->resetErrorBag();
        }

        $this->newTitle = '';
        $this->newDescription = '';
        $this->newTags = '';
        $this->newThumbnail = null;
        $this->showAskModal = true;
    }

    /**
     * Submit and create a new question.
     */
    public function submitQuestion(?string $title = null, ?string $description = null, ?string $tags = null): void
    {
        if (! Auth::check()) {
            $this->showAskModal = false;
            $this->dispatch('open-auth-modal', 'Ask a Question');

            return;
        }

        $this->resetValidation();
        if (method_exists($this, 'resetErrorBag')) {
            $this->resetErrorBag();
        }

        if ($title !== null) {
            $this->newTitle = $title;
        }
        if ($description !== null) {
            $this->newDescription = $description;
        }
        if ($tags !== null) {
            $this->newTags = $tags;
        }

        $this->validate();

        $cleanTitle = trim($this->newTitle);
        if (! Question::isTitleUnique($cleanTitle)) {
            $this->addError('newTitle', 'A question with this title already exists. Please choose a unique title.');

            return;
        }

        $tagList = array_values(array_filter(array_map('trim', explode(',', $this->newTags))));
        if (empty($tagList)) {
            $tagList = ['General'];
        }

        $thumbnailPath = null;
        if ($this->newThumbnail) {
            $thumbnailPath = $this->newThumbnail->store('thumbnails', 'public');
        }

        $user = Auth::user();

        Question::create([
            'user_id' => $user->_id ?? (string) ($user->id ?? 'anonymous'),
            'title' => $cleanTitle,
            'description' => $this->newDescription,
            'tags' => $tagList,
            'category' => $tagList,
            'thumbnail' => $thumbnailPath,
            'views_count' => 0,
            'votes_count' => 0,
            'shared_count' => 0,
            'answer_count' => 0,
            'likes_count' => 0,
            'is_closed' => false,
            'status' => 1,
            'follow' => [],
            'save' => [],
            'is_reported' => false,
        ]);

        $this->showAskModal = false;
        $this->newTitle = '';
        $this->newDescription = '';
        $this->newTags = '';
        $this->newThumbnail = null;

        $this->dispatch('toast', message: 'Question posted successfully! 🎉', type: 'success');
    }

    /**
     * Open the Edit Question modal and populate question data.
     */
    public function openEditModal(string $questionId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Edit a Question');

            return;
        }

        $this->resetValidation();
        if (method_exists($this, 'resetErrorBag')) {
            $this->resetErrorBag();
        }

        $q = Question::find($questionId);
        if (! $q) {
            $this->dispatch('toast', message: 'Question not found.', type: 'error');

            return;
        }

        $currentUserId = (string) (Auth::user()->_id ?? Auth::id() ?? '');
        $questionAuthorId = (string) ($q->user_id ?? ($q->user->_id ?? $q->user->id ?? ''));

        if ($currentUserId === '' || $currentUserId !== $questionAuthorId) {
            $this->dispatch('toast', message: 'You are not authorized to edit this question.', type: 'error');

            return;
        }

        $this->editingQuestionId = $questionId;
        $this->editTitle = (string) ($q->title ?? '');
        $this->editDescription = (string) ($q->description ?? '');
        $tags = $q->tags ?? $q->category ?? [];
        $this->editTags = is_array($tags) ? implode(', ', $tags) : (string) $tags;
        $this->existingThumbnail = $q->thumbnail ?? null;
        $this->editThumbnail = null;

        $this->dispatch('populate-edit-modal', [
            'id' => (string) ($q->_id ?? $q->id ?? $questionId),
            'title' => $this->editTitle,
            'description' => $this->editDescription,
            'tags' => $this->editTags,
            'thumbnail' => $this->existingThumbnail,
        ]);

        $this->showEditModal = true;
    }

    /**
     * Save updates to the currently edited question.
     */
    public function saveEdit(?string $title = null, ?string $description = null, ?string $tags = null): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Edit a Question');

            return;
        }

        if (! $this->editingQuestionId) {
            return;
        }

        $this->resetValidation();
        if (method_exists($this, 'resetErrorBag')) {
            $this->resetErrorBag();
        }

        if ($title !== null) {
            $this->editTitle = $title;
        }
        if ($description !== null) {
            $this->editDescription = $description;
        }
        if ($tags !== null) {
            $this->editTags = $tags;
        }

        $cleanTitle = trim($this->editTitle);
        if (empty($cleanTitle)) {
            $this->addError('editTitle', 'The question title is required.');

            return;
        }

        if (! Question::isTitleUnique($cleanTitle, $this->editingQuestionId)) {
            $this->addError('editTitle', 'A question with this title already exists. Please choose a unique title.');

            return;
        }

        $q = Question::find($this->editingQuestionId);
        if ($q) {
            $currentUserId = (string) (Auth::user()->_id ?? Auth::id() ?? '');
            $questionAuthorId = (string) ($q->user_id ?? ($q->user->_id ?? $q->user->id ?? ''));

            if ($currentUserId === '' || $currentUserId !== $questionAuthorId) {
                $this->dispatch('toast', message: 'You are not authorized to edit this question.', type: 'error');

                return;
            }

            $tagList = array_values(array_filter(array_map('trim', explode(',', $this->editTags))));
            if (empty($tagList)) {
                $tagList = ['General'];
            }

            $thumbnailPath = $this->existingThumbnail ?? $q->thumbnail;
            if ($this->editThumbnail) {
                $thumbnailPath = $this->editThumbnail->store('thumbnails', 'public');
            }

            $q->update([
                'title' => $cleanTitle,
                'slug' => Str::slug($cleanTitle),
                'description' => $this->editDescription,
                'tags' => $tagList,
                'category' => $tagList,
                'thumbnail' => $thumbnailPath,
            ]);

            if (property_exists($this, 'slug') && ! empty($q->slug)) {
                $this->slug = (string) $q->slug;
            }

            $this->dispatch('toast', message: 'Question updated successfully! ✨', type: 'success');
        }

        $this->showEditModal = false;
        $this->editingQuestionId = null;
        $this->editTitle = '';
        $this->editDescription = '';
        $this->editTags = '';
        $this->editThumbnail = null;
        $this->existingThumbnail = null;
    }

    /**
     * Search skills autocomplete for question tags.
     */
    public function searchSkills(string $query = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return Skill::query()
                ->select(['skills_title', 'name'])
                ->whereIn('skills_status', [1, '1'])
                ->limit(20)
                ->get()
                ->map(fn ($s) => trim((string) ($s->skills_title ?? $s->name ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $escaped = preg_quote($query, '/');

        return Skill::query()
            ->select(['skills_title', 'name'])
            ->whereIn('skills_status', [1, '1'])
            ->where(function ($q) use ($escaped) {
                $q->where('skills_title', 'regex', new Regex('^'.$escaped, 'i'))
                    ->orWhere('name', 'regex', new Regex('^'.$escaped, 'i'));
            })
            ->limit(15)
            ->get()
            ->map(fn ($s) => trim((string) ($s->skills_title ?? $s->name ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
