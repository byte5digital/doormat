import type { Dismissable } from '../types';

/**
 * Which notices a visitor closed. A dismissal is the key `doormat:<id>:<version>` in localStorage for a
 * notice that never comes back ("once"), in sessionStorage for one that comes back next session
 * ("session"), nowhere for one that comes back on every page ("always"). An edited notice has a new
 * version and so returns. Storage may be blocked (private mode, a policy): then nothing is remembered
 * and every notice shows.
 */
export function useDismissals() {
    function key(notice: Dismissable): string {
        return 'doormat:' + notice.id + ':' + notice.version;
    }

    function storageFor(notice: Dismissable): Storage | null {
        try {
            if (notice.frequency === 'once') {
                return window.localStorage;
            }
            if (notice.frequency === 'session') {
                return window.sessionStorage;
            }
        } catch {
            // storage blocked
        }

        return null;
    }

    function isDismissed(notice: Dismissable): boolean {
        try {
            const storage = storageFor(notice);

            return storage !== null && storage.getItem(key(notice)) !== null;
        } catch {
            return false;
        }
    }

    function remember(notice: Dismissable): void {
        try {
            storageFor(notice)?.setItem(key(notice), '1');
        } catch {
            // storage blocked: the notice shows again
        }
    }

    return { key, isDismissed, remember };
}
