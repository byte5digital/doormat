import { useBars } from './composables/useBars';
import { useDialog } from './composables/useDialog';
import { useDismissals } from './composables/useDismissals';
import { fromElement, isPreview } from './types';

/**
 * The script the addon's views inline (compiled to resources/dist/doormat.js): the notices the
 * visitor already closed go before anything shows, then the bars and the dialog come alive. A notice
 * in live preview stays, whatever was closed before.
 */
export function start(root: Document = document): void {
    const { isDismissed } = useDismissals();

    root.querySelectorAll('[data-doormat-notice]').forEach((el) => {
        if (!isPreview(el) && isDismissed(fromElement(el))) {
            el.remove();
        }
    });

    useBars(root);

    const dialog = root.querySelector<HTMLDialogElement>('[data-doormat-dialog]');
    if (dialog) {
        useDialog(dialog, { backdrop: root.querySelector<HTMLElement>('[data-doormat-backdrop]') });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => start());
} else {
    start();
}
