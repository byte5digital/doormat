import { afterEach, describe, expect, it, vi } from 'vitest';
import { useDismissals } from '../composables/useDismissals';

describe('useDismissals', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        localStorage.clear();
        sessionStorage.clear();
    });

    it('keys a dismissal by id and version', () => {
        expect(useDismissals().key({ id: 'a', version: 7, frequency: 'once' })).toBe('doormat:a:7');
    });

    it('remembers "once" for good, "session" for the session and "always" nowhere', () => {
        const { isDismissed, remember } = useDismissals();
        const once = { id: 'o', version: 1, frequency: 'once' } as const;
        const session = { id: 's', version: 1, frequency: 'session' } as const;
        const always = { id: 'a', version: 1, frequency: 'always' } as const;

        [once, session, always].forEach(remember);

        expect(localStorage.getItem('doormat:o:1')).toBe('1');
        expect(sessionStorage.getItem('doormat:s:1')).toBe('1');
        expect(isDismissed(once)).toBe(true);
        expect(isDismissed(session)).toBe(true);
        expect(isDismissed(always)).toBe(false);
    });

    it('lets an edited notice return', () => {
        const { isDismissed, remember } = useDismissals();
        remember({ id: 'n', version: 1, frequency: 'once' });

        expect(isDismissed({ id: 'n', version: 2, frequency: 'once' })).toBe(false);
    });

    it('shows every notice when storage is blocked, and throws nothing', () => {
        // A browser that blocks storage throws a SecurityError as soon as the page touches it.
        const blocked = () => {
            throw new DOMException('blocked', 'SecurityError');
        };
        vi.spyOn(window, 'localStorage', 'get').mockImplementation(blocked);
        vi.spyOn(window, 'sessionStorage', 'get').mockImplementation(blocked);
        const { isDismissed, remember } = useDismissals();
        const notice = { id: 'b', version: 1, frequency: 'once' } as const;

        expect(() => remember(notice)).not.toThrow();
        expect(isDismissed(notice)).toBe(false);
    });
});
