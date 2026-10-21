# Doormat

Site notices for Statamic: a message editors switch on in the control panel - a holiday closure,
a changed phone number, a warning about delays - that visitors see when they open the website.

## Documentation

- [Configuration](docs/configuration.md) - every setting: config file, settings page, the notice's fields,
  the tag's and the widget's options, commands, publish tags, permissions, translations
- [Frontend](docs/frontend.md) - the tag, the JSON endpoint, the dismissal rules, and building your own
  Vue component

## Requirements

- PHP 8.2+
- Statamic 6.35+ (Laravel 12 or 13)

## Installation

```bash
composer require byte5/doormat
php please doormat:install
php artisan vendor:publish --tag=doormat --force
```

The last line publishes the control panel's script to `public/vendor/doormat/build`; run it again after every
update (a site that commits its published assets commits that folder).

`doormat:install` writes the notices collection (`content/collections/doormat_notices.yaml`) and its
blueprint (`resources/blueprints/collections/doormat_notices/notice.yaml`) into the folders the site
configured for Statamic, with every site listed. Commit both files; an existing file is left alone
unless `--force` is given. The notices are listed under Content > Collections, for whoever may view the collection (Statamic's
collection permissions); the settings page is under Tools > Add-ons > Doormat.

## The notices

An entry of the collection: an internal name (the same in every language), a heading, a short Bard
text and an optional button per language; a type (info, success, warning, error), a publish date, an
optional take-down date, a pinned toggle (one notice at most), a dismissible toggle and how often a
closed notice returns (once per visitor, once per session, on every page) - one value for every
language. A notice shows on the sites it has a published localization on. `Byte5\Doormat\Notices::active($site)`
returns the live notices of a site, the pinned one first, then the newest.

## Showing them

Two ways; a site picks one.

**The tag**, rendered on the server:

```antlers
{{ doormat_notices }}
```

Put it right after `<body>`. It renders the view `config('doormat.view')` names: `modal` (a dialog for
the notices a visitor may close, one at a time with a pager; bars for the rest) or `notice` (bars
only). `view="notice"` and `site="en"` override per placement. As a pair it loops the notices for your
own markup; `{{ doormat_notices:script }}` prints the script alone. The views carry the data attributes
the script reads (`data-doormat-*`); publish them to restyle:

```bash
php artisan vendor:publish --tag=doormat-views
```

The script is written in TypeScript as framework-free composables - `useDismissals()`, `useDialog()`, `useBars()` in `resources/ts` - and shipped compiled in `resources/dist/doormat.js`, so a site needs no build. A site with a build of its own can import them from the package's source (`vendor/byte5/doormat/resources/ts`) for its own markup. The dialog opens with `show()`, under a consent banner's z-index (`--doormat-z-index`, 1000), never in
the browser's top layer. The view inlines it with
`data-cookieconsent="ignore"`; dismissals are kept in `localStorage` / `sessionStorage` as
`doormat:<id>:<version>`, so an edited notice returns.

With static caching the tag's output sits in cached pages: the addon flushes the static cache when a
notice is saved or deleted, and `doormat:flush-due` - scheduled every minute - when a publish or
take-down time passes. The scheduler must run.

**The endpoint**, for a site whose own JavaScript shows the notices:

```
GET /!/doormat/notices?site=<handle>
```

answers `{"data": [...], "meta": {"view": "modal", "delay": 0, "preview": false}}`, each notice with `id`, `version`, `heading`, `type`, `text` (HTML), `button_label`,
`button_url`, `pinned`, `dismissible`, `frequency`; the current site without the parameter, 404 for an
unknown one, never cached. Keep the storage keys of the addon's script if you write your own, and see
[Live preview](#live-preview) for the one parameter a site's script forwards.

## Live preview

An editor editing a notice clicks **Live Preview** and sees it, unsaved changes included, on a page of
the site as a visitor would: the dialog or the bar, in the notice's language. The page is the one
picked on the settings page (its version in the notice's language), else that language's homepage.

Notices have no URL, and Statamic offers live preview only to entries with one. The collection file
`doormat:install` writes names the addon's entry class and a preview target:

```yaml
entry_class: Byte5\Doormat\Entries\Notice
preview_targets:
  -
    label: 'Page with the notice'
    url: '{doormat_preview_url}'
    refresh: true
```

A site that installed the addon before adds these lines to `content/collections/doormat_notices.yaml` (`--force` would
overwrite the blueprint as well) and run `php artisan statamic:stache:clear && php artisan statamic:stache:warm`. The entry class extends whatever
entry class the site binds - the Stache's, the Eloquent driver's or a site's own - so the notices stay
where they are. Statamic hands the page the preview as `?live-preview=…&token=…`; while that token is
the live preview of a notice, the tag and the endpoint show that notice alone - whatever its dates, its
published state or the master switch - and nothing else.

**With the tag** there is nothing to do: on the preview page it renders the notice marked
`data-doormat-preview`, and the script opens it at once - no delay - whatever the editor closed before,
and closing it records nothing. Views published before keep working, but honour dismissals and the
delay until they carry the attribute (see the shipped views). A pair's own markup asks
`{{ doormat_notices:preview }}`.

**With a frontend of its own**, the site's script forwards the page's `token` to the endpoint and, when
`meta.preview` is true, shows the one notice it gets regardless of its own dismissals, without a delay
(`meta.delay` is 0) and without recording the close:

```js
import { useDismissals } from '../../vendor/byte5/doormat/resources/ts'; // or the site's own

const { isDismissed, remember } = useDismissals();
const token = new URLSearchParams(window.location.search).get('token');
const params = { site: 'en', ...(token ? { token } : {}) };

const { data } = await axios.get('/!/doormat/notices', { params });
const preview = data.meta.preview;

const notices = preview ? data.data : data.data.filter((notice) => !isDismissed(notice));
// show them; on close: if (!preview) remember(notice)
```

The endpoint also reads Statamic's `X-Statamic-Token` header. A missing, expired or unrelated token (one
of another entry) changes nothing: the visitor's notices as always. Where the application limits the
classes a cached value may unserialize into (`cache.serializable_classes`), the addon adds its entry
class, as Statamic adds its own; a site on the Eloquent driver may need the driver's entry and model
classes there too, for live preview of any entry.

## In the control panel

- **The list** shows each notice's status - Draft, Scheduled, Live or Taken down, worked out from its
  dates - with its type, publish and take-down date and whether it is pinned.
- **Show again to everyone**, an action on a notice (or several), brings it back for the visitors who
  closed it without editing its text. On the origin every language returns; on a localization, that
  language.
- **The settings page** (Tools > Add-ons > Doormat; the `edit byte5/doormat settings` permission):
  a master switch that keeps every notice off the site, the presentation (dialog or bars; required while
  the switch is on; until the settings are first saved the config file's `view` applies), whether the dialog waits - "No wait" or "Wait" with a number of seconds - before it opens,
  and the page a notice's live preview opens on (empty: the homepage). Saving it flushes the static
  cache. The endpoint hands the presentation and the delay to a site's own script as `meta`.

## Dashboard widget

A "Doormat" card, built on Statamic's own widget, listing, per language, the notices live right now - or "No Doormat notices active"
when none is. It notes when the master switch is off, and shows only to users who may view the
notices. Add it in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'doormat', 'width' => 50],
],
```

`'languages' => 'active'` lists only the languages that have a live notice; the default, `'all'`, lists every
language, with "No notices active" under those without one.

## Configuration

```bash
php artisan vendor:publish --tag=doormat-config
```

`collection` (default `doormat_notices`) and `view` (`modal` or `notice`; the settings page's choice wins).

## Translations

The blueprint's labels are English; translate them in the site's `lang/<locale>.json` like any other
blueprint's. The visitor strings are `doormat::notices.*`; translate them in
`lang/vendor/doormat/<locale>/notices.php` (`php artisan vendor:publish --tag=doormat-translations`).

## Development

```bash
composer install
composer check        # Pint, Larastan, Rector (dry run) and the tests
composer test
composer lint         # Pint, Laravel preset
composer stan         # Larastan, level 7
composer rector       # apply Rector's changes
npm install
npm test              # Vitest: the composables and the dialog
npm run typecheck
npm run build         # the visitor script (esbuild) and the control panel bundle (Vite) into resources/dist - commit the result
```

The tests run on Orchestra Testbench with the Stache, two sites and the addon's own blueprint. GitHub
Actions runs them on PHP 8.2 to 8.5 with Laravel 12 and 13, plus Pint, Larastan and Rector, and
the TypeScript (typecheck, Vitest, and a check that the committed build matches the source), for every
push and pull request. Rector skips the console command attributes, which need Laravel 13.

Releases are made by release-please from the conventional commits on `main`: it keeps a release pull
request with the changelog open; merging it tags the version Composer installs.


## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
