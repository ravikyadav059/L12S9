@props([
    'ans',
    'question',
    'votedAnswers' => [],
    'savedAnswers' => [],
    'followingUsers' => [],
    'followingAnswers' => [],
    'editingAnswerId' => null,
    'editingAnswerContent' => '',
])

@php
    $ansUser = $ans->user;
    $ansUserId = (string) ($ans->user_id ?? ($ansUser?->_id ?? $ansUser?->id ?? ''));
    $ansUserName = $ansUser?->name ?? 'User';
    $ansUserAvatar = $ansUser?->avatar ?? 'https://picsum.photos/seed/user/80/80.jpg';
    $isAnsOwner = Auth::check() && ((string) Auth::id() === $ansUserId);
    $isAnsVotedUp = ($votedAnswers[(string) $ans->id] ?? '') === 'upvote';
    $isAnsVotedDown = ($votedAnswers[(string) $ans->id] ?? '') === 'downvote';
    $isAnsSaved = in_array((string) $ans->id, $savedAnswers, true);
    $isFollowingAnsAuthor = $ansUserId !== '' && in_array($ansUserId, $followingUsers, true);
    $isQOwner = Auth::check() && ((string) Auth::id() === (string) ($question->user_id ?? ''));
    $ansReplies = $ans->replies ?? collect([]);
    $replyCount = count($ansReplies);
@endphp

<div 
    wire:key="ans-{{ $ans->id }}"
    x-data="{ showComments: false }"
    class="bg-white dark:bg-zinc-900 border {{ $ans->is_accepted ? 'border-emerald-500 border-l-4 border-l-emerald-500 dark:border-emerald-500' : 'border-zinc-200 dark:border-zinc-800' }} rounded-2xl p-6 shadow-xs space-y-4 transition-all"
>
    <!-- Accepted Solution Badge -->
    @if($ans->is_accepted)
        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-md text-xs font-bold border border-emerald-200 dark:border-emerald-800/80">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <span>Accepted Answer</span>
        </div>
    @endif

    <!-- Answer Header (Author Avatar, Name, Follow, Time) -->
    <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800 flex-wrap gap-2">
        <div class="flex items-center gap-3 min-w-0">
            <div class="relative w-10 h-10 rounded-full overflow-hidden shrink-0 ring-2 ring-[#198BEA]/20">
                <img src="{{ $ansUserAvatar }}" alt="{{ $ansUserName }}" class="w-full h-full object-cover">
                @if($ans->is_accepted)
                    <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white flex items-center justify-center text-white">
                        <svg class="w-2 h-2" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h5 class="text-xs font-bold text-zinc-900 dark:text-white">{{ $ansUserName }}</h5>

                    @if(($ans->votes_count ?? 0) > 5)
                        <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                            Expert
                        </span>
                    @endif

                    @if($ansUserId !== '' && !$isAnsOwner)
                        <button 
                            wire:click="toggleFollowUser('{{ $ansUserId }}', '{{ addslashes($ansUserName) }}')"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold transition-all cursor-pointer {{ $isFollowingAnsAuthor ? 'bg-[#ecfdf5] text-[#059669] border border-[#10b981] dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-600' : 'text-[#198BEA] border border-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40' }}"
                        >
                            @if($isFollowingAnsAuthor)
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <polyline points="16 11 18 13 22 9"/>
                                </svg>
                                <span>Following</span>
                            @else
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <line x1="19" x2="19" y1="8" y2="14"/>
                                    <line x1="22" x2="16" y1="11" y2="11"/>
                                </svg>
                                <span>Follow</span>
                            @endif
                        </button>
                    @endif
                </div>
                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
                     Answers {{ $ans->created_at ? $ans->created_at->diffForHumans() : 'recently' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Answer Body or Edit Form -->
    @if($editingAnswerId === (string) $ans->id)
        <div class="space-y-3">
            <textarea 
                wire:model="editingAnswerContent" 
                rows="5"
                placeholder="Edit your answer..."
                class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 text-zinc-900 dark:text-zinc-100 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all resize-y min-h-[120px]"
            ></textarea>
            <div class="flex items-center justify-end gap-2">
                <button wire:click="cancelEditAnswer" type="button" class="px-3.5 py-1.5 text-xs font-semibold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg cursor-pointer transition-colors">Cancel</button>
                <button wire:click="saveEditAnswer" type="button" class="px-4 py-1.5 text-xs font-semibold bg-[#198BEA] hover:bg-[#1476c9] text-white rounded-lg shadow-xs cursor-pointer transition-colors">Save Changes</button>
            </div>
        </div>
    @else
        <div class="qa-rich-content text-zinc-800 dark:text-zinc-200">
            {!! $ans->content !!}
        </div>
    @endif

    <!-- Answer Action Bar (Upvote, Downvote, Accept, Follow, Save, Share, Edit, Comment, Delete) -->
    <div class="flex items-center justify-between flex-wrap gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/80">
        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
            <!-- Upvote Answer -->
            <button 
                wire:click="toggleAnswerVote('{{ $ans->id }}', 'upvote')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isAnsVotedUp ? 'border border-[#198BEA] text-[#198BEA] bg-[#f0f7ff] dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-600' : 'text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800' }}"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                </svg>
                <span>Upvote</span>
                @if(($ans->votes_count ?? 0) > 0)
                    <span class="font-bold ml-0.5">{{ $ans->votes_count }}</span>
                @endif
            </button>

            <!-- Downvote Answer -->
            <button 
                wire:click="toggleAnswerVote('{{ $ans->id }}', 'downvote')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isAnsVotedDown ? 'border border-rose-400 text-rose-600 bg-rose-50 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-500' : 'text-zinc-700 dark:text-zinc-200 hover:text-rose-600 hover:bg-rose-50/60 dark:hover:bg-zinc-800' }}"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76 1.04m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/>
                </svg>
                <span>Downvote</span>
            </button>

            <!-- Accept / Accepted Button (For Question Owner) -->
            @if($isQOwner)
                <button 
                    wire:click="toggleAcceptAnswer('{{ $ans->id }}')"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $ans->is_accepted ? 'bg-emerald-50 text-emerald-700 border border-emerald-400 dark:bg-emerald-950/60 dark:text-emerald-300' : 'text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50/60' }}"
                    title="{{ $ans->is_accepted ? 'Click to unaccept' : 'Mark as accepted solution' }}"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $ans->is_accepted ? 'Accepted' : 'Accept' }}</span>
                </button>
            @endif

            <!-- Follow Answer -->
            @php
                $ansFollowCount = count($ans->follow ?? []);
                $isAnsFollowed = in_array((string) $ans->id, $followingAnswers, true);
            @endphp
            <button 
                wire:click="toggleFollowAnswer('{{ $ans->id }}')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isAnsFollowed ? 'bg-[#ecfdf5] text-[#059669] border border-[#10b981] dark:bg-emerald-950/50 dark:text-emerald-300' : 'text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800' }}"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span>{{ $ansFollowCount }} Follow</span>
            </button>

            <!-- Save Answer -->
            <button 
                wire:click="toggleAnswerSave('{{ $ans->id }}')"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isAnsSaved ? 'bg-amber-50 text-amber-600 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300' : 'text-zinc-700 dark:text-zinc-200 hover:text-amber-600 hover:bg-amber-50/60 dark:hover:bg-zinc-800' }}"
            >
                <svg class="w-3.5 h-3.5 {{ $isAnsSaved ? 'fill-amber-500 text-amber-500' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                </svg>
                <span>{{ $isAnsSaved ? 'Saved' : 'Save' }}</span>
            </button>

            <!-- Share Answer -->
            <button 
                type="button"
                @click="$dispatch('open-share-modal', { url: '{{ route('questions.show', $question->slug ?: (string)$question->_id) }}#ans-{{ $ans->id }}', title: 'Answer to {{ addslashes($question->title) }}', type: 'answer', header: 'Share Answer', subtitle: 'Share this answer across your network' })"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                <span>Share</span>
            </button>
        </div>

        <!-- Right Actions: Edit, Comment & Delete for Answer Owner / Question Owner -->
        <div class="flex items-center gap-1.5">
            @if($isAnsOwner)
                <button 
                    wire:click="startEditAnswer('{{ $ans->id }}')"
                    type="button"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:text-[#198BEA] hover:border-[#198BEA] hover:bg-sky-50 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                    <span>Edit</span>
                </button>
            @endif

            <!-- Comment Toggle Button (matching qa-comment-btn) -->
            <button 
                type="button"
                @click="showComments = !showComments; if(showComments) $nextTick(() => $refs.commentInput && $refs.commentInput.focus())"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-[#198BEA] border border-sky-200 dark:border-sky-900/50 hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40 transition-all cursor-pointer"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <span>Comment</span>
            </button>

            @if($isAnsOwner || $isQOwner)
                <button 
                    wire:click="deleteAnswer('{{ $ans->id }}')"
                    wire:confirm="Are you sure you want to delete this answer? This action cannot be undone."
                    type="button"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-all cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    <span>Delete</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Comments Section Accordion (qa-acmt matching QDeatils.html) -->
    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800/80">
        <div class="flex items-center justify-between">
            <button 
                type="button"
                @click="showComments = !showComments"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-[#198BEA] hover:text-[#1476c9] cursor-pointer transition-colors py-1"
            >
                <svg 
                    class="w-3.5 h-3.5 transition-transform duration-200" 
                    :class="{ 'rotate-90': showComments }"
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2.5" 
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span>{{ $replyCount }} {{ $replyCount === 1 ? 'Comment' : 'Comments' }}</span>
            </button>
        </div>

        <!-- Collapsible Comments Area -->
        <div x-show="showComments" x-collapse class="mt-3 space-y-3">
            <!-- List of existing comments -->
            @if($replyCount > 0)
                <div class="space-y-2.5 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($ansReplies as $reply)
                        @php
                            $repUser = $reply->user;
                            $repId = (string) ($reply->_id ?? $reply->id);
                            $repUserId = (string) ($reply->user_id ?? ($repUser?->_id ?? $repUser?->id ?? ''));
                            $repUserName = $repUser?->name ?? 'User';
                            $repUserAvatar = $repUser?->avatar ?? 'https://picsum.photos/seed/user/80/80.jpg';
                            $repContent = $reply->content ?? '';
                            $repLikes = is_array($reply->likes ?? null) ? $reply->likes : [];
                            $repLikesCount = (int) ($reply->likes_count ?? count($repLikes));
                            $isRepLiked = Auth::check() && in_array((string) Auth::id(), $repLikes, true);
                            $isRepOwner = Auth::check() && ((string) Auth::id() === $repUserId);
                            $repCreatedAt = $reply->created_at ? $reply->created_at->diffForHumans() : 'recently';
                        @endphp

                        <div class="pt-2.5 first:pt-0 flex gap-2.5">
                            <img src="{{ $repUserAvatar }}" alt="{{ $repUserName }}" class="w-7 h-7 rounded-full object-cover shrink-0 ring-1 ring-zinc-200 dark:ring-zinc-700">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap text-xs">
                                    <span class="font-bold text-zinc-900 dark:text-white">{{ $repUserName }}</span>
                                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $repCreatedAt }}</span>
                                </div>
                                <p class="text-xs text-zinc-700 dark:text-zinc-300 mt-0.5 leading-relaxed">
                                    {{ $repContent }}
                                </p>
                                <div class="flex items-center gap-3 mt-1.5">
                                    <button 
                                        wire:click="toggleCommentLike('{{ $repId }}')"
                                        type="button" 
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold transition-colors cursor-pointer {{ $isRepLiked ? 'text-[#198BEA]' : 'text-zinc-500 hover:text-[#198BEA] dark:text-zinc-400' }}"
                                    >
                                        <svg class="w-3 h-3 {{ $isRepLiked ? 'fill-[#198BEA]' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                        </svg>
                                        <span>{{ $repLikesCount }}</span>
                                    </button>

                                    @if($isRepOwner || $isQOwner)
                                        <button 
                                            wire:click="deleteComment('{{ $repId }}')"
                                            wire:confirm="Are you sure you want to delete this comment?"
                                            type="button" 
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-zinc-400 hover:text-rose-600 transition-colors cursor-pointer"
                                            title="Delete comment"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>Delete</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Inline Comment Input Box -->
            <div class="flex items-center gap-2.5 pt-2">
                @auth
                    <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}" class="w-7 h-7 rounded-full object-cover shrink-0 ring-1 ring-[#198BEA]/30">
                @else
                    <div class="w-7 h-7 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center shrink-0 text-zinc-500 text-xs">?</div>
                @endauth

                <div class="flex-1 relative flex items-center">
                    <input 
                        wire:model="commentInputs.{{ $ans->id }}"
                        wire:keydown.enter="addComment('{{ $ans->id }}')"
                        x-ref="commentInput"
                        type="text" 
                        placeholder="Add a comment..." 
                        class="w-full pl-3.5 pr-16 py-1.5 text-xs bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#198BEA]"
                    >
                    <button 
                        wire:click="addComment('{{ $ans->id }}')"
                        type="button"
                        class="absolute right-1 px-3 py-1 bg-[#198BEA] hover:bg-[#1476c9] text-white rounded-full text-[11px] font-bold transition-all cursor-pointer"
                    >
                        Post
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
