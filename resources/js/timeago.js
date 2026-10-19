/**
 * ============================================================================
 * Centralized Client-Side TimeAgo Engine
 * ============================================================================
 * 
 * Purpose:
 *   Automatically updates relative timestamps (e.g. "just now", "12 seconds ago",
 *   "5 minutes ago", "2 hours ago") in real-time across the UI with 0% server/database load.
 *
 * How to use in Blade / HTML:
 *   <time datetime="2026-10-19T13:00:00Z"
 *         data-qa-timeago="2026-10-19T13:00:00Z"
 *         data-qa-prefix="Asked ">
 *       Asked 5 minutes ago
 *   </time>
 *
 * Features:
 *   - Detailed seconds resolution: Displays exact seconds when under 1 minute (e.g. "12 seconds ago").
 *   - Smart Dynamic Ticking: Ticks every 1 second when there are recent items (< 60s) on screen;
 *     automatically shifts to 10 seconds for older items.
 *   - Battery & CPU Optimized: Automatically pauses when browser tab is inactive/hidden,
 *     and resumes immediately upon focus.
 *   - Livewire & SPA Ready: Re-scans elements after Livewire morph/commit and navigated events.
 * ============================================================================
 */

(function () {
    if (typeof window === 'undefined' || window.TimeAgoEngine) {
        return; // Avoid double initialization or non-browser environments
    }

    const TICK_FAST = 1000;   // 1 second when items are < 60s old for smooth live seconds
    const TICK_SLOW = 10000;  // 10 seconds when all items are >= 1 min old to conserve CPU
    let currentInterval = null;
    let currentIntervalSpeed = null;

    function formatRelativeTime(timestampMs) {
        if (!timestampMs || isNaN(timestampMs)) return null;

        const now = Date.now();
        const diffSec = Math.floor((now - timestampMs) / 1000);

        // Future or instantaneous
        if (diffSec <= 0) return 'just now';

        // Under 1 minute: detailed second-by-second
        if (diffSec === 1) return '1 second ago';
        if (diffSec < 60) return `${diffSec} seconds ago`;

        // 1 to 2 minutes
        if (diffSec < 120) return '1 minute ago';

        // Minutes (up to 60 minutes)
        const minutes = Math.floor(diffSec / 60);
        if (minutes < 60) return `${minutes} minutes ago`;

        // 1 to 2 hours
        if (diffSec < 7200) return '1 hour ago';

        // Hours (up to 24 hours)
        const hours = Math.floor(diffSec / 3600);
        if (hours < 24) return `${hours} hours ago`;

        // 1 to 2 days
        if (diffSec < 172800) return '1 day ago';

        // Days (up to 30 days)
        const days = Math.floor(diffSec / 86400);
        if (days < 30) return `${days} days ago`;

        // 1 to 2 months
        if (diffSec < 5184000) return '1 month ago';

        // Months (up to 12 months)
        const months = Math.floor(diffSec / (30.4 * 86400));
        if (months < 12) return `${months} months ago`;

        // Years
        const years = Math.floor(diffSec / (365.25 * 86400));
        if (years <= 1) return '1 year ago';
        return `${years} years ago`;
    }

    function updateElement(el) {
        const raw = el.getAttribute('data-qa-timeago');
        if (!raw) return null;

        let timestampMs = isNaN(raw) ? Date.parse(raw) : Number(raw);
        if (isNaN(timestampMs)) return null;

        // If in seconds (e.g. 10 digits), convert to ms
        if (timestampMs < 10000000000) {
            timestampMs *= 1000;
        }

        const relativeStr = formatRelativeTime(timestampMs);
        if (!relativeStr) return null;

        const prefix = el.getAttribute('data-qa-prefix') || '';
        const suffix = el.getAttribute('data-qa-suffix') || '';
        const newText = prefix + relativeStr + suffix;

        if (el.textContent !== newText) {
            el.textContent = newText;
        }

        return Date.now() - timestampMs;
    }

    function updateAll() {
        const elements = document.querySelectorAll('[data-qa-timeago]');
        let hasRecentItem = false;

        for (let i = 0; i < elements.length; i++) {
            const ageMs = updateElement(elements[i]);
            if (ageMs !== null && ageMs < 60000) {
                hasRecentItem = true;
            }
        }

        // Adjust ticker rate: fast (1s) if there are seconds-level items, slow (10s) otherwise
        setTimerSpeed(hasRecentItem ? TICK_FAST : TICK_SLOW);
    }

    function setTimerSpeed(speed) {
        if (document.hidden) {
            stop();
            return;
        }

        if (currentIntervalSpeed === speed && currentInterval !== null) {
            return; // Already running at this speed
        }

        stop();
        currentIntervalSpeed = speed;
        currentInterval = setInterval(updateAll, speed);
    }

    function start() {
        updateAll();
    }

    function stop() {
        if (currentInterval !== null) {
            clearInterval(currentInterval);
            currentInterval = null;
            currentIntervalSpeed = null;
        }
    }

    // Battery & CPU optimization: pause when tab is inactive, resume immediately on focus
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    // Auto-start on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    // Immediate MutationObserver: instantly updates newly inserted answer cards or elements
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(() => {
            updateAll();
        });
        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        } else {
            document.addEventListener('DOMContentLoaded', () => {
                observer.observe(document.body, { childList: true, subtree: true });
            });
        }
    }

    // SPA navigation & Livewire hooks
    document.addEventListener('livewire:navigated', updateAll);

    function registerLivewireHooks() {
        if (window.Livewire && window.Livewire.hook) {
            window.Livewire.hook('morph.updated', updateAll);
            window.Livewire.hook('commit', updateAll);
        }
    }

    if (window.Livewire) {
        registerLivewireHooks();
    } else {
        document.addEventListener('livewire:init', registerLivewireHooks);
    }

    window.addEventListener('qa-refresh-timeago', updateAll);

    // Export to global scope
    window.TimeAgoEngine = {
        format: formatRelativeTime,
        updateAll: updateAll,
        start: start,
        stop: stop
    };
})();
