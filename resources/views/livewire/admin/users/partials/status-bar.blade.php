<?php

use App\Models\User;
use Livewire\Volt\Component;

/**
 * Admin User Edit - Status Bar Component (Isolated Volt Child Component)
 *
 * Provides instant, isolated toggles for user profile status, verification,
 * publisher/reviewer approvals, badge type, role, and conference participation.
 */
new class extends Component {
    public User $user;

    public string $profile_status = '0';
    public string $verified = '0';
    public string $publisher_status = '0';
    public string $reviewer_status = '0';
    public string $badge_type = '';
    public string $role = '';
    public bool $enable_conference = false;

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->profile_status = (string) $this->user->getStatusCode();
        $this->verified = ! empty($this->user->verified) ? '1' : '0';

        $rawPub = strtolower(trim((string) ($this->user->publisher_status ?? '0')));
        $this->publisher_status = match ($rawPub) {
            '1', 'accept', 'accepted' => '1',
            '2', 'reject', 'rejected' => '2',
            default => '0',
        };

        $rawRev = strtolower(trim((string) ($this->user->reviewer_status ?? '0')));
        $this->reviewer_status = match ($rawRev) {
            '1', 'accept', 'accepted' => '1',
            '2', 'reject', 'rejected' => '2',
            default => '0',
        };

        $this->badge_type = (string) ($this->user->badge_type ?? '');
        $this->role = (string) ($this->user->role ?? '');
        $this->enable_conference = (bool) ($this->user->enable_conference ?? false);
    }

    /**
     * Directly update user profile status (0=Pending, 1=Active, 2=Re-Document, 3=Reject).
     */
    public function updateProfileStatus(string $val): void
    {
        $this->profile_status = $val;
        $this->user->status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Profile status updated.');
    }

    /**
     * Directly update verified user status flag (0=No, 1=Yes).
     */
    public function updateVerifiedUser(string $val): void
    {
        $this->verified = $val;
        $this->user->verified = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Verified user status updated.');
    }

    /**
     * Directly update publisher application status (0=Pending, 1=Accept, 2=Reject).
     */
    public function updatePublisherStatus(string $val): void
    {
        $this->publisher_status = $val;
        $this->user->publisher_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Publisher status updated.');
    }

    /**
     * Directly update reviewer application status (0=Pending, 1=Accept, 2=Reject).
     */
    public function updateReviewerStatus(string $val): void
    {
        $this->reviewer_status = $val;
        $this->user->reviewer_status = (int) $val;
        $this->user->save();
        session()->flash('quick_message', 'Reviewer status updated.');
    }

    /**
     * Directly update badge type (1=Researcher, 2=Reviewer, 3=Professional).
     */
    public function updateBadgeType(string $val): void
    {
        $this->badge_type = $val;
        $this->user->badge_type = $val;
        $this->user->save();
        session()->flash('quick_message', 'Badge type updated.');
    }

    /**
     * Directly update system user role (scholar, admin, publisher, python).
     */
    public function updateUserRole(string $val): void
    {
        $this->role = $val;
        $this->user->role = $val;
        $this->user->save();
        session()->flash('quick_message', 'User role updated.');
    }

    /**
     * Toggle the conference participation access flag for all journals.
     */
    public function toggleConference(): void
    {
        $this->enable_conference = ! $this->enable_conference;
        $this->user->enable_conference = $this->enable_conference;
        $this->user->save();
        session()->flash('quick_message', 'Conference settings updated.');
    }
}; ?>

<div 
    class="bg-white dark:bg-[#111c26] rounded-2xl p-6 border border-zinc-200/90 dark:border-zinc-800 shadow-sm space-y-4"
    x-data="{
        profileStatus: @entangle('profile_status'),
        verified: @entangle('verified'),
        publisherStatus: @entangle('publisher_status'),
        reviewerStatus: @entangle('reviewer_status'),
        badgeType: @entangle('badge_type'),
        userRole: @entangle('role'),
        enableConference: @entangle('enable_conference'),
        setProfile(val) {
            this.profileStatus = val;
            $wire.updateProfileStatus(val);
        },
        setVerified(val) {
            this.verified = val;
            $wire.updateVerifiedUser(val);
        },
        setPublisher(val) {
            this.publisherStatus = val;
            $wire.updatePublisherStatus(val);
        },
        setReviewer(val) {
            this.reviewerStatus = val;
            $wire.updateReviewerStatus(val);
        }
    }"
>
    @if (session()->has('quick_message'))
        <div class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between transition">
            <span>{{ session('quick_message') }}</span>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-xs cursor-pointer">✕</button>
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-x-6 gap-y-4">
        <!-- 1. Profile Status Toggle Pills -->
        <div>
            <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                Profile Status:
            </label>
            <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                <button 
                    type="button" 
                    @click="setProfile('0')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="profileStatus === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Pending
                </button>
                <button 
                    type="button" 
                    @click="setProfile('1')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="profileStatus === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Active
                </button>
                <button 
                    type="button" 
                    @click="setProfile('2')"
                    wire:loading.attr="disabled"
                    class="px-3 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer leading-tight text-center active:scale-95"
                    :class="profileStatus === '2' ? 'bg-amber-600 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Re-Document
                </button>
                <button 
                    type="button" 
                    @click="setProfile('3')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="profileStatus === '3' ? 'bg-[#e03a45] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Reject
                </button>
            </div>
        </div>

        <!-- 2. Verified User Toggle Pills -->
        <div>
            <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                Verified User:
            </label>
            <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                <button 
                    type="button" 
                    @click="setVerified('0')"
                    wire:loading.attr="disabled"
                    class="px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="verified === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    No
                </button>
                <button 
                    type="button" 
                    @click="setVerified('1')"
                    wire:loading.attr="disabled"
                    class="px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="verified === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Yes
                </button>
            </div>
        </div>

        <!-- 3. Publisher Status Toggle Pills -->
        <div>
            <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                Publisher Status:
            </label>
            <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                <button 
                    type="button" 
                    @click="setPublisher('0')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="publisherStatus === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Pending
                </button>
                <button 
                    type="button" 
                    @click="setPublisher('1')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="publisherStatus === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Accept
                </button>
                <button 
                    type="button" 
                    @click="setPublisher('2')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="publisherStatus === '2' ? 'bg-[#e03a45] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Reject
                </button>
            </div>
        </div>

        <!-- 4. Reviewer Status Toggle Pills -->
        <div>
            <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                Reviewer Status:
            </label>
            <div class="inline-flex items-center bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-full p-1 shadow-inner border border-zinc-300/40 dark:border-zinc-700/40">
                <button 
                    type="button" 
                    @click="setReviewer('0')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="reviewerStatus === '0' ? 'bg-zinc-700 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Pending
                </button>
                <button 
                    type="button" 
                    @click="setReviewer('1')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="reviewerStatus === '1' ? 'bg-[#00c02b] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Accept
                </button>
                <button 
                    type="button" 
                    @click="setReviewer('2')"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-150 cursor-pointer active:scale-95"
                    :class="reviewerStatus === '2' ? 'bg-[#e03a45] text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-zinc-700/40'"
                >
                    Reject
                </button>
            </div>
        </div>

        <!-- 5. Badge Type Selector -->
        <div>
            <label for="badge_type" class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                Badge Type:
            </label>
            <div class="relative">
                <select 
                    name="badge_type" 
                    id="badge_type" 
                    class="profile_badge_type appearance-none h-9.5 pl-3.5 pr-8 bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-xl text-xs font-bold text-zinc-800 dark:text-zinc-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand/30 border border-zinc-300/40 dark:border-zinc-700/40 hover:border-zinc-400 dark:hover:border-zinc-600 transition" 
                    user_id="{{ (string) $user->_id }}"
                    wire:change="updateBadgeType($event.target.value)"
                    x-model="badgeType"
                >
                    <option value="" :selected="badgeType === ''">Select Badge Type</option>
                    <option value="1" :selected="badgeType === '1'">Researcher</option>
                    <option value="2" :selected="badgeType === '2'">Reviewer</option>
                    <option value="3" :selected="badgeType === '3'">Professional</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-zinc-500">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- 6. User Role Selector -->
        <div>
            <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2">
                User Role:
            </label>
            <div class="relative">
                <select 
                    wire:change="updateUserRole($event.target.value)"
                    x-model="userRole"
                    class="appearance-none h-9.5 pl-3.5 pr-8 bg-[#dcdfe3] dark:bg-zinc-800/90 rounded-xl text-xs font-bold text-zinc-800 dark:text-zinc-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand/30 border border-zinc-300/40 dark:border-zinc-700/40 hover:border-zinc-400 dark:hover:border-zinc-600 transition min-w-[150px]"
                >
                    <option value="" :selected="userRole === ''">Select User Role</option>
                    <option value="scholar" :selected="userRole === 'scholar'">Scholar</option>
                    <option value="admin" :selected="userRole === 'admin'">Administrator</option>
                    <option value="publisher" :selected="userRole === 'publisher'">Publisher</option>
                    <option value="python" :selected="userRole === 'python'">Python</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-zinc-500">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- 7. Change Password Trigger Button -->
        <div>
            <button 
                type="button" 
                @click="$dispatch('open-password-modal')"
                class="h-9.5 px-5 rounded-full border-2 border-[#1991EB] text-[#1991EB] hover:bg-[#1991EB] hover:text-white dark:hover:bg-[#1991EB] dark:hover:text-white text-xs font-bold transition-all duration-200 cursor-pointer shadow-xs active:scale-95 flex items-center gap-1.5 whitespace-nowrap"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <span>Change Password</span>
            </button>
        </div>

        <!-- 8. Enable Conference for All Journals Toggle -->
        <div class="flex items-center gap-3 h-9.5 px-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs">
            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                Enable Conference for All Journals:
            </span>
            <button 
                type="button" 
                wire:click="toggleConference"
                @click="enableConference = !enableConference"
                role="switch"
                :aria-checked="enableConference ? 'true' : 'false'"
                class="relative inline-flex h-5.5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand/30"
                :class="enableConference ? 'bg-brand' : 'bg-zinc-300 dark:bg-zinc-700'"
            >
                <span class="pointer-events-none inline-block size-4.5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out" :class="enableConference ? 'translate-x-4.5' : 'translate-x-0'"></span>
            </button>
            <span class="text-xs font-bold whitespace-nowrap" :class="enableConference ? 'text-brand' : 'text-zinc-500'" x-text="enableConference ? 'Enabled' : 'Disabled'">
                {{ $enable_conference ? 'Enabled' : 'Disabled' }}
            </span>
        </div>
    </div>
</div>
