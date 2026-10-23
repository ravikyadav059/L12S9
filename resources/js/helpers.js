/**
 * Global ISSN Formatter (formats input or string as XXXX-XXXX)
 *
 * @param {Event|HTMLInputElement|string} target - The input event, element, or raw string
 * @param {string|null} field - Optional Livewire property name to update
 * @param {object|null} wire - Optional Livewire component instance ($wire)
 * @returns {string} The formatted ISSN string
 */
export function formatIssn(target, field = null, wire = null) {
    let raw = '';
    let inputEl = null;

    if (target && target.target) {
        inputEl = target.target;
        raw = inputEl.value || '';
    } else if (target && typeof target === 'object' && 'value' in target) {
        inputEl = target;
        raw = inputEl.value || '';
    } else if (typeof target === 'string') {
        raw = target;
    }

    raw = (raw || '').toUpperCase();
    let clean = '';
    for (let i = 0; i < raw.length && clean.length < 8; i++) {
        let ch = raw[i];
        if (clean.length < 7) {
            if (/[0-9]/.test(ch)) clean += ch;
        } else {
            if (/[0-9X]/.test(ch)) clean += ch;
        }
    }

    if (clean.length > 4) {
        clean = clean.substring(0, 4) + '-' + clean.substring(4);
    }

    if (inputEl) {
        inputEl.value = clean;
    }

    if (field) {
        if (wire && typeof wire.set === 'function') {
            wire.set(field, clean);
        } else if (typeof window !== 'undefined' && window.Livewire && inputEl) {
            const lwEl = inputEl.closest('[wire\\:id]');
            if (lwEl) {
                const component = window.Livewire.find(lwEl.getAttribute('wire:id'));
                if (component && typeof component.set === 'function') {
                    component.set(field, clean);
                }
            }
        }
    }

    return clean;
}

if (typeof window !== 'undefined') {
    window.formatIssn = formatIssn;
}
