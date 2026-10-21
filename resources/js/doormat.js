(function () {
    'use strict';

    // Doormat: hides the notices the visitor already closed, opens the dialog for the rest
    // (one at a time, previous and next), and remembers a dismissal under the notice's
    // frequency. Everything it needs is in the data attributes of the markup; the markup
    // itself is the view's business. Nothing here is global.

    function key(el) {
        return 'doormat:' + el.getAttribute('data-doormat-id') + ':' + el.getAttribute('data-doormat-version');
    }

    function storageFor(el) {
        var frequency = el.getAttribute('data-doormat-frequency');

        try {
            if (frequency === 'once') {
                return window.localStorage;
            }
            if (frequency === 'session') {
                return window.sessionStorage;
            }
        } catch (e) {
            // Storage blocked: nothing is remembered, the notice shows.
        }

        return null;
    }

    function isDismissed(el) {
        try {
            var storage = storageFor(el);

            return storage !== null && storage.getItem(key(el)) !== null;
        } catch (e) {
            return false;
        }
    }

    function remember(el) {
        try {
            var storage = storageFor(el);

            if (storage !== null) {
                storage.setItem(key(el), '1');
            }
        } catch (e) {
            // Storage blocked: the notice will show again.
        }
    }

    function remove(el) {
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    function toArray(list) {
        return Array.prototype.slice.call(list);
    }

    function bars() {
        toArray(document.querySelectorAll('.doormat-bar [data-doormat-close]')).forEach(function (button) {
            button.addEventListener('click', function () {
                var bar = button.closest('[data-doormat-notice]');

                remember(bar);
                remove(bar);
            });
        });
    }

    function dialog() {
        var dialog = document.querySelector('[data-doormat-dialog]');
        var backdrop = document.querySelector('[data-doormat-backdrop]');

        if (!dialog) {
            return;
        }

        var notices = toArray(dialog.querySelectorAll('[data-doormat-notice]'));

        if (notices.length === 0) {
            remove(dialog);
            remove(backdrop);

            return;
        }

        var index = 0;
        var prev = dialog.querySelector('[data-doormat-prev]');
        var next = dialog.querySelector('[data-doormat-next]');
        var counter = dialog.querySelector('[data-doormat-counter]');
        var footer = dialog.querySelector('[data-doormat-footer]');
        var dotsBox = dialog.querySelector('[data-doormat-dots]');
        var dots = [];

        // "Notice :current of :total" from the view's data-doormat-format, else "1 / 3".
        function position(el, i) {
            var format = el && el.getAttribute('data-doormat-format');

            return format
                ? format.replace(':current', String(i + 1)).replace(':total', String(notices.length))
                : (i + 1) + ' / ' + notices.length;
        }

        if (dotsBox && notices.length > 1) {
            notices.forEach(function (notice, i) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'doormat-dialog__dot';
                dot.setAttribute('aria-label', position(dotsBox, i));
                dot.appendChild(document.createElement('span'));
                dot.addEventListener('click', function () {
                    index = i;
                    render();
                });
                dotsBox.appendChild(dot);
                dots.push(dot);
            });
        }

        function render() {
            notices.forEach(function (notice, i) {
                notice.hidden = i !== index;
            });
            dots.forEach(function (dot, i) {
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

        function close() {
            notices.forEach(remember);
            if (dialog.open) {
                dialog.close();
            }
            remove(dialog);
            remove(backdrop);
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            }
        }

        if (prev) {
            prev.addEventListener('click', function () {
                if (index > 0) {
                    index -= 1;
                    render();
                }
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                if (index < notices.length - 1) {
                    index += 1;
                    render();
                }
            });
        }
        toArray(dialog.querySelectorAll('[data-doormat-close]')).forEach(function (button) {
            button.addEventListener('click', close);
        });
        if (backdrop) {
            backdrop.addEventListener('click', close);
            backdrop.hidden = false;
        }
        document.addEventListener('keydown', onKeydown);

        // The dialog's own cancel (a browser closing it) counts as a close too.
        dialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            close();
        });

        render();
        dialog.setAttribute('tabindex', '-1');
        // Opened as a plain dialog, not in the browser's top layer: it stays under a consent banner's z-index and the page is not frozen.
        dialog.show();
        dialog.focus();
    }

    function start() {
        toArray(document.querySelectorAll('[data-doormat-notice]')).forEach(function (el) {
            if (isDismissed(el)) {
                remove(el);
            }
        });

        bars();
        dialog();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
