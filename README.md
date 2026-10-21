# Doormat

Site notices for Statamic: a message editors switch on in the control panel - a holiday closure,
a changed phone number, a warning about delays - that visitors see when they open the website.

## Documentation

- [Configuration](docs/configuration.md) - every setting: config file, settings page, the notice's fields,
  the tag's and the widget's options, commands, publish tags, permissions, translations
- [Frontend](docs/frontend.md) - the tag, the JSON endpoint, the dismissal rules, live preview, and
  building your own Vue component

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

### Upgrading to 0.4

The type field is `notice_type` now, in the blueprint and in what the tag and the endpoint hand out - Statamic's
entry list answers a column named `type` with the collection's title, so the Type column stayed empty.
An update script renames the field in the installed blueprint and moves the stored values; Statamic runs it after
`composer update`, or by hand with `php please updates:run 0.3.0 --package=byte5/doormat`. A site with its own
frontend or published views reads `notice_type` instead of `type`.

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

answers `{"data": [...], "meta": {"view": "modal", "delay": 0, "preview": false}}`, each notice with `id`, `version`, `heading`, `notice_type`, `text` (HTML), `button_label`,
`button_url`, `pinned`, `dismissible`, `frequency`; the current site without the parameter, 404 for an
unknown one, never cached. Keep the storage keys of the addon's script if you write your own, and see
[Live preview](docs/frontend.md#live-preview) for the one parameter a site's script forwards.

## Live preview

**Live Preview** on a notice shows it, unsaved changes included, on a page of the site as a visitor
would see it - the settings page picks the page, else the homepage. The collection file `doormat:install`
writes enables it; an installation from before 0.3 adds two keys to its collection file, and a site with
its own frontend forwards the page's `token`. See [Frontend](docs/frontend.md#live-preview).

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
