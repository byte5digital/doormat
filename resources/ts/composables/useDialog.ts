import { fromElement } from '../types';
import { useDismissals } from './useDismissals';

export interface DialogOptions {
    /** The overlay behind the dialog; a click on it closes. */
    backdrop?: HTMLElement | null;
}

/**
 * The dialog of the notices a visitor may close: one notice at a time, previous and next, the counter
 * ("Notice 1 of 3" from the view's data-doormat-format, else "1 / 3") and the dots; closing - its
 * buttons, the backdrop, Escape - remembers every notice it held. It opens with show(), not
 * showModal(): a modal dialog sits in the browser's top layer above a consent banner and freezes the
 * page, so the visitor could not answer the banner. The view's data-doormat-delay waits that many
 * seconds before opening.
 */
export function useDialog(dialog: HTMLDialogElement, options: DialogOptions = {}) {
    const { remember } = useDismissals();
    const backdrop = options.backdrop ?? null;
    const notices = Array.from(dialog.querySelectorAll<HTMLElement>('[data-doormat-notice]'));
    const prev = dialog.querySelector<HTMLButtonElement>('[data-doormat-prev]');
    const next = dialog.querySelector<HTMLButtonElement>('[data-doormat-next]');
    const counter = dialog.querySelector<HTMLElement>('[data-doormat-counter]');
    const footer = dialog.querySelector<HTMLElement>('[data-doormat-footer]');
    const dotsBox = dialog.querySelector<HTMLElement>('[data-doormat-dots]');
    const dots: HTMLButtonElement[] = [];
    let index = 0;

    function position(el: Element | null, i: number): string {
        const format = el?.getAttribute('data-doormat-format');

        return format
            ? format.replace(':current', String(i + 1)).replace(':total', String(notices.length))
            : `${i + 1} / ${notices.length}`;
    }

    function render(): void {
        notices.forEach((notice, i) => {
            notice.hidden = i !== index;
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('doormat-dialog__dot--current', i === index);
        });
        if (counter) {
            counter.textContent = notices.length > 1 ? position(counter, index) : '';
        }
        if (footer) {
            footer.hidden = notices.length < 2;
        }
        if (prev) {
            prev.disabled = index === 0;
            prev.hidden = notices.length < 2;
        }
        if (next) {
            next.disabled = index === notices.length - 1;
            next.hidden = notices.length < 2;
        }
    }

    function go(i: number): void {
        index = Math.max(0, Math.min(notices.length - 1, i));
        render();
    }

    function onKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    }

    function close(): void {
        notices.forEach((notice) => remember(fromElement(notice)));
        if (dialog.open) {
            dialog.close();
        }
        dialog.remove();
        backdrop?.remove();
        document.removeEventListener('keydown', onKeydown);
    }

    function open(): void {
        if (backdrop) {
            backdrop.hidden = false;
        }
        dialog.setAttribute('tabindex', '-1');
        dialog.show();
        dialog.focus();
    }

    if (notices.length === 0) {
        dialog.remove();
        backdrop?.remove();

        return { count: 0, index: () => index, go, open, close };
    }

    if (dotsBox && notices.length > 1) {
        notices.forEach((_, i) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'doormat-dialog__dot';
            dot.setAttribute('aria-label', position(dotsBox, i));
            dot.appendChild(document.createElement('span'));
            dot.addEventListener('click', () => go(i));
            dotsBox.appendChild(dot);
            dots.push(dot);
        });
    }

    prev?.addEventListener('click', () => go(index - 1));
    next?.addEventListener('click', () => go(index + 1));
    dialog.querySelectorAll('[data-doormat-close]').forEach((button) => button.addEventListener('click', close));
    backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', onKeydown);
    // The browser's own cancel (a closing gesture) counts as a close too.
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    render();

    const delay = Number.parseInt(dialog.getAttribute('data-doormat-delay') ?? '0', 10);
    if (delay > 0) {
        window.setTimeout(open, delay * 1000);
    } else {
        open();
    }

    return { count: notices.length, index: () => index, go, open, close };
}
