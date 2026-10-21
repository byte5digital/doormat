import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { start } from '../doormat';

function notice(id: string, heading: string, frequency = 'once', dismissible = true, tag = 'section'): string {
    const cls = tag === 'section' ? 'doormat-notice' : 'doormat-bar';
    const close = tag === 'div' && dismissible ? '<button data-doormat-close>x</button>' : '';

    return `<${tag} class="${cls}" data-doormat-notice data-doormat-id="${id}" data-doormat-version="1" data-doormat-frequency="${frequency}" data-doormat-dismissible="${dismissible}" ${tag === 'section' ? 'hidden' : ''}><h2>${heading}</h2>${close}</${tag}>`;
}

function page(sections: string, bars = '', delay = 0): void {
    document.body.innerHTML = `${bars}
        <div data-doormat-backdrop hidden></div>
        <dialog data-doormat-dialog data-doormat-delay="${delay}">
            <div data-doormat-counter data-doormat-format="Notice :current of :total"></div>
            <button data-doormat-close>x</button>
            ${sections}
            <footer data-doormat-footer>
                <button data-doormat-prev>prev</button>
                <div data-doormat-dots></div>
                <button data-doormat-next>next</button>
            </footer>
        </dialog>`;
}

/** Marks the page's notices and dialog as the tag does in a notice's live preview. */
function asPreview(): void {
    document.querySelectorAll('[data-doormat-notice], [data-doormat-dialog]').forEach((el) => el.setAttribute('data-doormat-preview', ''));
}

const visible = () => Array.from(document.querySelectorAll<HTMLElement>('dialog [data-doormat-notice]')).filter((n) => !n.hidden).map((n) => n.textContent);

describe('the addon script', () => {
    beforeEach(() => {
        localStorage.clear();
        sessionStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('opens the dialog without the top layer, one notice at a time, with counter and dots', () => {
        page(notice('a', 'First') + notice('b', 'Second'));
        const showModal = vi.spyOn(HTMLDialogElement.prototype, 'showModal');

        start();

        const dialog = document.querySelector('dialog')!;
        expect(dialog.open).toBe(true);
        expect(showModal).not.toHaveBeenCalled();
        expect(visible()).toEqual(['First']);
        expect(document.querySelector('[data-doormat-counter]')!.textContent).toBe('Notice 1 of 2');
        expect(document.querySelectorAll('.doormat-dialog__dot')).toHaveLength(2);
        expect(document.querySelector<HTMLElement>('[data-doormat-backdrop]')!.hidden).toBe(false);
    });

    it('pages with next, previous and the dots', () => {
        page(notice('a', 'First') + notice('b', 'Second'));
        start();

        document.querySelector<HTMLButtonElement>('[data-doormat-next]')!.click();
        expect(visible()).toEqual(['Second']);
        expect(document.querySelector<HTMLButtonElement>('[data-doormat-next]')!.disabled).toBe(true);

        document.querySelector<HTMLButtonElement>('[data-doormat-prev]')!.click();
        expect(visible()).toEqual(['First']);

        document.querySelectorAll<HTMLButtonElement>('.doormat-dialog__dot')[1].click();
        expect(visible()).toEqual(['Second']);
        expect(document.querySelectorAll('.doormat-dialog__dot--current')).toHaveLength(1);
    });

    it('hides the footer for a single notice', () => {
        page(notice('a', 'Only'));
        start();

        expect(document.querySelector<HTMLElement>('[data-doormat-footer]')!.hidden).toBe(true);
        expect(document.querySelector('[data-doormat-counter]')!.textContent).toBe('');
    });

    it('remembers every notice of the dialog on close, so the next page shows nothing', () => {
        page(notice('a', 'First') + notice('b', 'Second', 'session'));
        start();
        document.querySelector<HTMLButtonElement>('dialog > [data-doormat-close]')!.click();

        expect(document.querySelector('dialog')).toBeNull();
        expect(document.querySelector('[data-doormat-backdrop]')).toBeNull();
        expect(localStorage.getItem('doormat:a:1')).toBe('1');
        expect(sessionStorage.getItem('doormat:b:1')).toBe('1');

        page(notice('a', 'First') + notice('b', 'Second', 'session'));
        start();
        expect(document.querySelector('dialog')).toBeNull();
    });

    it('closes on Escape and on the backdrop', () => {
        page(notice('a', 'First'));
        start();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(document.querySelector('dialog')).toBeNull();

        localStorage.clear();
        page(notice('a', 'First'));
        start();
        document.querySelector<HTMLElement>('[data-doormat-backdrop]')!.click();
        expect(document.querySelector('dialog')).toBeNull();
    });

    it('waits the delay before it opens', () => {
        vi.useFakeTimers();
        page(notice('a', 'First'), '', 3);
        start();

        expect(document.querySelector('dialog')!.open).toBe(false);
        vi.advanceTimersByTime(3000);
        expect(document.querySelector('dialog')!.open).toBe(true);
    });

    it('closes a bar for good, while a bar without a close button stays', () => {
        page('', notice('x', 'Closable', 'once', true, 'div') + notice('y', 'Fixed', 'once', false, 'div'));
        start();

        document.querySelector<HTMLButtonElement>('.doormat-bar [data-doormat-close]')!.click();
        expect(document.querySelectorAll('.doormat-bar')).toHaveLength(1);
        expect(localStorage.getItem('doormat:x:1')).toBe('1');
    });

    it('opens a live preview at once, whatever the visitor closed and the delay, and remembers nothing', () => {
        vi.useFakeTimers();
        localStorage.setItem('doormat:a:1', '1');
        page(notice('a', 'Preview'), '', 3);
        asPreview();

        start();

        expect(document.querySelector('dialog')!.open).toBe(true);
        expect(visible()).toEqual(['Preview']);

        localStorage.clear();
        document.querySelector<HTMLButtonElement>('dialog > [data-doormat-close]')!.click();
        expect(document.querySelector('dialog')).toBeNull();
        expect(localStorage.getItem('doormat:a:1')).toBeNull();
    });

    it('keeps a previewed bar the visitor closed before and closes it without remembering', () => {
        sessionStorage.setItem('doormat:x:1', '1');
        page('', notice('x', 'Preview', 'session', true, 'div'));
        asPreview();

        start();
        expect(document.querySelectorAll('.doormat-bar')).toHaveLength(1);

        sessionStorage.clear();
        document.querySelector<HTMLButtonElement>('.doormat-bar [data-doormat-close]')!.click();
        expect(document.querySelectorAll('.doormat-bar')).toHaveLength(0);
        expect(sessionStorage.getItem('doormat:x:1')).toBeNull();
    });
});
