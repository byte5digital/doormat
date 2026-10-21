import { fromElement, isPreview } from '../types';
import { useDismissals } from './useDismissals';

/** The bars' close buttons: a closed bar is remembered - unless it is a live preview - and removed. */
export function useBars(root: ParentNode = document) {
    const { remember } = useDismissals();

    function close(bar: Element): void {
        if (!isPreview(bar)) {
            remember(fromElement(bar));
        }
        bar.remove();
    }

    root.querySelectorAll('.doormat-bar [data-doormat-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const bar = button.closest('[data-doormat-notice]');
            if (bar) {
                close(bar);
            }
        });
    });

    return { close };
}
