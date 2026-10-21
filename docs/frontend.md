# Frontend

Two ways to show notices to visitors. A site picks one.

- **The tag** renders the notices into the page with the addon's views and script. Nothing to build.
- **The endpoint** answers the notices as JSON for a site that shows them with its own JavaScript, for
  example a Vue component. The page itself may come from the static cache; the endpoint never is.

## The tag

```antlers
<body>
    {{ doormat_notices }}
    …
```

Options: [Configuration](configuration.md#the-tag). The views (`modal`, `notice`) are publishable
(`--tag=doormat-views`); keep their `data-doormat-*` attributes when you restyle them, the script reads
them:

| Attribute | On | Meaning |
|-----------|----|---------|
| `data-doormat-notice`, `-id`, `-version`, `-frequency`, `-dismissible` | each notice element | The notice and its dismissal rules |
| `data-doormat-dialog`, `-delay` | the `<dialog>` | The dialog and its delay in seconds |
| `data-doormat-prev`, `-next`, `-counter`, `-dots`, `-footer`, `-close` | the dialog's controls | Paging and closing; `-format` on the counter and dots holds "Notice :current of :total" |
| `data-doormat-backdrop` | the overlay | A click closes the dialog |

## The endpoint

```
GET /!/doormat/notices?site=de
```

```json
{
    "data": [
        {
            "id": "0490e8b8-…",
            "version": 1792594616,
            "heading": "Closed from 23 December to 3 January",
            "type": "warning",
            "text": "<p>Orders received by 18 December ship before Christmas.</p>",
            "button_label": "Contact us",
            "button_url": "/en/contact",
            "pinned": true,
            "dismissible": true,
            "frequency": "once"
        }
    ],
    "meta": { "view": "modal", "delay": 0 }
}
```

### The notice fields

| Field | Type | Meaning |
|-------|------|---------|
| `id` | string | The entry's id |
| `version` | int | A Unix timestamp of the last change - the notice's or its original's, whichever is later. Part of the dismissal key, so an edited notice returns. |
| `heading` | string | The title visitors see; plain text, escape it |
| `type` | `info` / `success` / `warning` / `error` | Colour and icon |
| `text` | string | HTML from the editor (Bard); render it as HTML |
| `button_label`, `button_url` | string or null | Both set, or both null |
| `pinned` | bool | The order already puts it first |
| `dismissible` | bool | False: show it as a bar without a close button |
| `frequency` | `once` / `session` / `always` | How a closed notice comes back |

`data` is ordered: the pinned notice first, then the newest. `meta.view` is the settings page's
presentation (`modal` or `notice`), `meta.delay` the seconds the dialog waits (0: none). An empty `data`
means nothing to show - none is live, or the settings switched notices off.

## The dismissal rules

A closed notice is remembered in the browser under the key `doormat:<id>:<version>`:

| `frequency` | Stored in | Comes back |
|-------------|-----------|------------|
| `once` | `localStorage` | Never - until the notice is edited |
| `session` | `sessionStorage` | In the next browser session |
| `always` | nowhere | On every page |

Storage can be blocked (private mode, a policy); then nothing is remembered and every notice shows. Do
not write these rules yourself: import them from the package, as below, so your frontend and the tag
agree.

## Building your own Vue component

The package ships its browser logic as framework-free TypeScript composables in
`vendor/byte5/doormat/resources/ts`:

| Export | What it does |
|--------|--------------|
| `useDismissals()` | `{ key, isDismissed, remember }` for a `{ id, version, frequency }` |
| `useDialog(dialog, { backdrop })` | Drives a dialog of the addon's markup (paging, closing, delay) |
| `useBars(root)` | The bars' close buttons |
| `fromElement(el)` | A notice element's data attributes as `{ id, version, frequency }` |
| `Dismissable`, `Frequency` | Types |

A Vue component needs only `useDismissals`; it renders the markup itself.

### 1. Make the source importable

`vite.config.js`:

```js
import { fileURLToPath } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@doormat': fileURLToPath(new URL('./vendor/byte5/doormat/resources/ts', import.meta.url)),
        },
    },
    // …
});
```

`tsconfig.json`, so the editor and `vue-tsc` find it too:

```json
{
    "compilerOptions": {
        "baseUrl": ".",
        "paths": { "@doormat": ["vendor/byte5/doormat/resources/ts"] }
    }
}
```

Vite compiles the TypeScript with the rest of your bundle; no build in the package is needed.

### 2. The component

A Vue 3 component (`<script setup>`): bars for the notices that cannot be closed, the others in a dialog,
one at a time.

```vue
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useDismissals } from '@doormat';
import type { Frequency } from '@doormat';

interface Notice {
    id: string;
    version: number;
    heading: string;
    type: 'info' | 'success' | 'warning' | 'error';
    text: string;
    button_label: string | null;
    button_url: string | null;
    pinned: boolean;
    dismissible: boolean;
    frequency: Frequency;
}

const props = defineProps<{ site: string }>();
const { isDismissed, remember } = useDismissals();

const notices = ref<Notice[]>([]);
const view = ref<'modal' | 'notice'>('modal');
const index = ref(0);
const open = ref(false);
const dialog = ref<HTMLDialogElement | null>(null);
let timer = 0;

// "Bars" shows every notice as a bar; the dialog presentation only those that cannot be closed.
const bars = computed(() => (view.value === 'notice' ? notices.value : notices.value.filter((n) => !n.dismissible)));
const inDialog = computed(() => (view.value === 'notice' ? [] : notices.value.filter((n) => n.dismissible)));
const current = computed(() => inDialog.value[index.value] ?? null);

onMounted(async () => {
    const response = await fetch(`/!/doormat/notices?site=${encodeURIComponent(props.site)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        return; // no notices; the page works without them
    }

    const { data, meta } = await response.json();
    view.value = meta?.view === 'notice' ? 'notice' : 'modal';
    notices.value = data.filter((notice: Notice) => !isDismissed(notice));

    if (inDialog.value.length > 0) {
        timer = window.setTimeout(openDialog, Math.max(0, meta?.delay ?? 0) * 1000);
    }
});

onBeforeUnmount(() => {
    window.clearTimeout(timer);
    document.removeEventListener('keydown', onKeydown);
});

async function openDialog() {
    open.value = true;
    document.addEventListener('keydown', onKeydown);
    await nextTick();
    // show(), not showModal(): a modal dialog sits in the top layer, above a cookie banner the
    // visitor must be able to answer first.
    dialog.value?.show();
    dialog.value?.focus();
}

function close() {
    inDialog.value.forEach(remember); // closing dismisses every notice the dialog held
    open.value = false;
    document.removeEventListener('keydown', onKeydown);
}

function closeBar(notice: Notice) {
    remember(notice);
    notices.value = notices.value.filter((other) => other.id !== notice.id);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}
</script>

<template>
    <div class="doormat">
        <div v-for="notice in bars" :key="notice.id" :class="['doormat-bar', `doormat-bar--${notice.type}`]" role="region" :aria-label="notice.heading">
            <strong>{{ notice.heading }}</strong>
            <div v-html="notice.text"></div>
            <a v-if="notice.button_url" :href="notice.button_url">{{ notice.button_label }}</a>
            <button v-if="notice.dismissible" type="button" aria-label="Close" @click="closeBar(notice)">×</button>
        </div>

        <template v-if="open && current">
            <div class="doormat-backdrop" @click="close"></div>
            <dialog ref="dialog" :class="['doormat-dialog', `doormat-dialog--${current.type}`]" tabindex="-1" aria-label="Notices">
                <h2>{{ current.heading }}</h2>
                <div v-html="current.text"></div>
                <a v-if="current.button_url" :href="current.button_url">{{ current.button_label }}</a>
                <footer v-if="inDialog.length > 1">
                    <button type="button" :disabled="index === 0" aria-label="Previous" @click="index--">‹</button>
                    <span>{{ index + 1 }} / {{ inDialog.length }}</span>
                    <button type="button" :disabled="index === inDialog.length - 1" aria-label="Next" @click="index++">›</button>
                </footer>
                <button type="button" aria-label="Close" @click="close">×</button>
            </dialog>
        </template>
    </div>
</template>
```

Give the backdrop and the dialog `position: fixed` and a `z-index` below your consent banner's. The
dialog is not in the browser's top layer, so `z-index` decides what lies above it.

### 3. Place it

Print it once per page, first in the page's flow so the bars come first, with the page's site handle.
In Antlers:

```antlers
<doormat-notices site="{{ site:handle }}"></doormat-notices>
```

In Blade, with Statamic's current site:

```blade
<doormat-notices site="{{ \Statamic\Facades\Site::current()->handle() }}"></doormat-notices>
```

### Vue 2.7

The same component works on Vue 2.7 with `defineComponent` and the Options API, or with `<script setup>`
(Vue 2.7 supports it); replace `onBeforeUnmount` with `beforeDestroy` in the Options API. If your bundle
compiles in-DOM templates, register the component globally and keep the element inside the Vue root.
