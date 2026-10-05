<?php

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';

    public string $newAnswer = '';

    public array $votedAnswers = [];

    public array $savedQuestions = [];

    public array $followingQuestions = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->savedQuestions = session('qa_saved_questions', []);
        $this->followingQuestions = session('qa_following_questions', []);

        // Record a view count on the question
        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->first();

        if ($question) {
            $question->increment('views_count');
        }
    }

    public function submitAnswer(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Answer a Question');

            return;
        }

        if (empty(trim($this->newAnswer))) {
            $this->addError('newAnswer', 'Please enter your answer.');

            return;
        }

        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->firstOrFail();

        $user = Auth::user();

        Answer::create([
            'question_id' => (string) $question->id,
            'user_id' => $user->_id ?? (string) ($user->id ?? 'anonymous'),
            'content' => $this->newAnswer,
            'votes_count' => 0,
            'is_accepted' => false,
            'status' => 1,
        ]);

        $question->increment('answer_count');

        $this->newAnswer = '';
        $this->dispatch('toast', message: 'Answer posted successfully! 🙌', type: 'success');
    }

    public function toggleVote(string $type = 'up'): void
    {
        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->first();

        if (! $question) {
            return;
        }

        $delta = ($type === 'up') ? 1 : -1;
        $newVotes = max(0, (int) ($question->votes_count ?? 0) + $delta);
        $question->update(['votes_count' => $newVotes]);

        $this->dispatch('toast', message: $type === 'up' ? 'Question upvoted! 👍' : 'Vote recorded', type: 'info');
    }

    public function toggleSave(string $questionId): void
    {
        if (in_array($questionId, $this->savedQuestions, true)) {
            $this->savedQuestions = array_values(array_diff($this->savedQuestions, [$questionId]));
            $this->dispatch('toast', message: 'Removed from bookmarks', type: 'info');
        } else {
            $this->savedQuestions[] = $questionId;
            $this->dispatch('toast', message: 'Saved to your bookmarks! 🔖', type: 'success');
        }

        session(['qa_saved_questions' => $this->savedQuestions]);
    }

    public function toggleFollow(string $questionId): void
    {
        if (in_array($questionId, $this->followingQuestions, true)) {
            $this->followingQuestions = array_values(array_diff($this->followingQuestions, [$questionId]));
            $this->dispatch('toast', message: 'Unfollowed question', type: 'info');
        } else {
            $this->followingQuestions[] = $questionId;
            $this->dispatch('toast', message: 'Following question updates! 🔔', type: 'success');
        }

        session(['qa_following_questions' => $this->followingQuestions]);
    }

    public function with(): array
    {
        $question = Question::query()
            ->with(['user', 'answers.user'])
            ->where(function ($q) {
                $q->where('slug', $this->slug)
                    ->orWhere('_id', $this->slug);
            })
            ->whereNotIn('status', [0, '0', false])
            ->firstOrFail();

        $popularQuestions = Question::query()
            ->whereNotIn('status', [0, '0', false])
            ->where('_id', '!=', $question->id)
            ->select(['_id', 'title', 'slug', 'answer_count', 'is_closed'])
            ->orderByDesc('views_count')
            ->limit(5)
            ->get();

        return [
            'question' => $question,
            'answers' => $question->answers ?? collect([]),
            'popularQuestions' => $popularQuestions,
        ];
    }
}; ?>

<div class="min-h-screen bg-[#f9fafb] dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 py-6 sm:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumbs Navigation -->
        <nav class="flex items-center gap-2 text-xs text-text-secondary dark:text-zinc-400 mb-6 font-medium">
            <a href="{{ route('home') }}" class="hover:text-[#198BEA] transition" wire:navigate>Home</a>
            <span>/</span>
            <a href="{{ route('questions.index') }}" class="hover:text-[#198BEA] transition" wire:navigate>Questions</a>
            <span>/</span>
            <span class="text-zinc-800 dark:text-zinc-200 truncate max-w-md">{{ $question->title }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- MAIN CONTENT AREA (Left 8 cols) -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- Main Question Card -->
                <article class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-xs">
                    
                    <!-- Question Header & Author Info -->
                    <div class="flex items-center justify-between flex-wrap gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800/80 mb-5">
                        <div class="flex items-center gap-3">
                            @php
                                $author = $question->user;
                                $authorName = $author?->fullname ?? ($author?->first_name ? $author->first_name.' '.$author->last_name : 'Scholar Contributor');
                                $authorInitial = strtoupper(substr($authorName, 0, 1) ?: 'S');
                                $authorAvatar = $author?->photo ?? $author?->avatar ?? null;
                            @endphp

                            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-[#198BEA] to-sky-400 text-white flex items-center justify-center font-bold text-sm shadow-xs overflow-hidden shrink-0">
                                @if($authorAvatar)
                                    <img src="{{ $authorAvatar }}" alt="{{ $authorName }}" class="w-full h-full object-cover">
                                @else
                                    {{ $authorInitial }}
                                @endif
                            </div>

                            <div>
                                <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                    {{ $authorName }}
                                </h4>
                                <p class="text-xs text-text-secondary dark:text-zinc-400">
                                    Asked {{ $question->created_at ? $question->created_at->diffForHumans() : 'recently' }}
                                </p>
                            </div>
                        </div>

                        <!-- Solved Badge -->
                        @if($question->is_closed)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 rounded-full text-xs font-bold border border-emerald-200 dark:border-emerald-800">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Solved
                            </span>
                        @endif
                    </div>

                    <!-- Question Title -->
                    <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white leading-tight mb-4">
                        {{ $question->title }}
                    </h1>

                    <!-- Question Body (Rich HTML format) -->
                    <div class="prose prose-zinc dark:prose-invert max-w-none text-sm sm:text-base leading-relaxed text-zinc-700 dark:text-zinc-200 mb-6 [&>p]:mb-3 [&>ul]:list-disc [&>ul]:pl-5 [&>ol]:list-decimal [&>ol]:pl-5 [&>a]:text-[#198BEA] [&>a]:underline [&>code]:bg-zinc-100 dark:[&>code]:bg-zinc-800 [&>code]:px-1.5 [&>code]:py-0.5 [&>code]:rounded [&>code]:text-xs [&>blockquote]:border-l-4 [&>blockquote]:border-[#198BEA] [&>blockquote]:pl-4 [&>blockquote]:italic">
                        {!! $question->description !!}
                    </div>

                    <!-- Thumbnail Image (if present) -->
                    @if(!empty($question->thumbnail))
                        @php
                            $thumbUrl = Str::startsWith($question->thumbnail, ['http://', 'https://']) 
                                ? $question->thumbnail 
                                : (Str::startsWith($question->thumbnail, ['storage/', '/storage/'])
                                    ? asset($question->thumbnail)
                                    : asset('storage/' . $question->thumbnail));
                        @endphp
                        <div class="mb-6 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 max-w-xl bg-zinc-100 dark:bg-zinc-800">
                            <img src="{{ $thumbUrl }}" alt="{{ $question->title }}" class="w-full max-h-80 object-cover" loading="lazy">
                        </div>
                    @endif

                    <!-- Question Tags -->
                    @php
                        $tags = $question->tags ?? $question->category ?? [];
                        if (is_string($tags)) {
                            $tags = array_map('trim', explode(',', $tags));
                        }
                    @endphp
                    @if(!empty($tags))
                        <div class="flex flex-wrap gap-2 mb-6">
                            @foreach($tags as $tag)
                                @if(trim($tag) !== '')
                                    <span class="px-3 py-1 rounded-md text-xs font-semibold bg-[#eaf5ff] text-[#198BEA] dark:bg-sky-950/60 dark:text-sky-300 select-none">
                                        {{ trim($tag) }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <!-- Stats Bar & Actions -->
                    <div class="flex items-center justify-between flex-wrap gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800/80">
                        
                        <!-- Stats Counts -->
                        <div class="flex items-center gap-4 text-xs text-text-secondary dark:text-zinc-400 font-medium">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-text-secondary dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                <span><strong class="font-bold text-text-main dark:text-zinc-100">{{ $question->answer_count ?? 0 }}</strong> Answers</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-text-secondary dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span><strong class="font-bold text-text-main dark:text-zinc-100">{{ number_format($question->views_count ?? 0) }}</strong> Views</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-text-secondary dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                <span><strong class="font-bold text-text-main dark:text-zinc-100">{{ number_format($question->votes_count ?? 0) }}</strong> Votes</span>
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 flex-wrap">
                            @auth
                                <button 
                                    wire:click="toggleVote('up')"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#198BEA] text-white hover:bg-[#1476c9] transition cursor-pointer shadow-xs"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                    <span>Upvote</span>
                                </button>

                                <button 
                                    wire:click="toggleSave('{{ $question->id }}')"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-amber-500 hover:border-amber-400 transition cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5 {{ in_array($question->id, $savedQuestions, true) ? 'fill-amber-500 text-amber-500' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                    <span>{{ in_array($question->id, $savedQuestions, true) ? 'Saved' : 'Save' }}</span>
                                </button>
                            @endauth

                            <!-- Share Button (Triggers Global Component Modal) -->
                            <button 
                                type="button"
                                @click="$dispatch('open-share-modal', { url: window.location.href, title: '{{ addslashes($question->title) }}', type: 'question', header: 'Share Question', subtitle: 'Share this question across networks' })"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-[#198BEA] hover:border-[#198BEA] transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                <span>Share</span>
                            </button>
                        </div>
                    </div>
                </article>

                <!-- Answers Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <span>{{ count($answers) }}</span>
                        <span>{{ count($answers) === 1 ? 'Answer' : 'Answers' }}</span>
                    </h3>

                    @forelse($answers as $ans)
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-xs space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-sky-100 text-[#198BEA] flex items-center justify-center font-bold text-xs">
                                        {{ strtoupper(substr($ans->user?->fullname ?? $ans->user?->first_name ?? 'A', 0, 1)) }}
                                    </div>
                                    <div>
                                        <h5 class="text-xs font-bold text-zinc-900 dark:text-white">{{ $ans->user?->fullname ?? $ans->user?->first_name ?? 'Scholar User' }}</h5>
                                        <span class="text-[11px] text-text-secondary dark:text-zinc-400">{{ $ans->created_at ? $ans->created_at->diffForHumans() : 'recently' }}</span>
                                    </div>
                                </div>
                                @if($ans->is_accepted)
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">✓ Accepted Answer</span>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                {!! nl2br(e($ans->content)) !!}
                            </div>
                        </div>
                    @empty
                        <div class="bg-white dark:bg-zinc-900 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 text-center text-zinc-400 text-sm">
                            No answers yet. Be the first to answer this question!
                        </div>
                    @endforelse
                </div>

                <!-- Post Your Answer Card -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-7 shadow-xs space-y-4">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">Your Answer</h3>
                    
                    <textarea 
                        wire:model="newAnswer" 
                        rows="5" 
                        placeholder="Write your detailed answer or solution here..."
                        class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#198BEA] text-zinc-900 dark:text-white resize-y"
                    ></textarea>

                    @error('newAnswer')
                        <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center justify-end">
                        @guest
                            <button 
                                @click="$dispatch('open-auth-modal', 'Answer a Question')" 
                                type="button"
                                class="px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98"
                            >
                                Post Your Answer
                            </button>
                        @else
                            <button 
                                wire:click="submitAnswer" 
                                type="button"
                                class="px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98"
                            >
                                Post Your Answer
                            </button>
                        @endguest
                    </div>
                </div>

            </div>

            <!-- RIGHT SIDEBAR (4 cols) -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Ask Question CTA Box -->
                <div class="bg-gradient-to-br from-[#198BEA] to-sky-600 text-white rounded-2xl p-6 shadow-md shadow-sky-500/20 space-y-3">
                    <h3 class="text-base font-bold">Have a similar question?</h3>
                    <p class="text-xs text-sky-100 leading-relaxed">
                        Join discussions and get answers from scholars, researchers, and developers worldwide.
                    </p>
                    <a 
                        href="{{ route('questions.index') }}" 
                        wire:navigate
                        class="inline-block w-full text-center py-2.5 bg-white text-[#198BEA] hover:bg-sky-50 font-bold text-xs rounded-xl shadow-xs transition"
                    >
                        Browse All Questions
                    </a>
                </div>

                <!-- Related / Popular Questions Widget -->
                @if($popularQuestions->isNotEmpty())
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Popular Questions
                        </h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($popularQuestions as $pop)
                                <a 
                                    href="{{ route('questions.show', $pop->slug ?: (string)$pop->_id) }}" 
                                    wire:navigate 
                                    class="block py-3 group first:pt-0 last:pb-0"
                                >
                                    <h5 class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors line-clamp-2 leading-snug">
                                        {{ $pop->title }}
                                    </h5>
                                    <span class="text-[11px] text-text-secondary dark:text-zinc-400 mt-1 block">
                                        {{ $pop->answer_count ?? 0 }} {{ ($pop->answer_count ?? 0) === 1 ? 'answer' : 'answers' }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

        </div>
    </div>

    <!-- Auth Prompt Modal -->
    <x-modals.auth />
</div>
