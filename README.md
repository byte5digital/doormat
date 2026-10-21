# Doormat

Site notices for Statamic: a message editors switch on in the control panel - a holiday closure,
a changed phone number, a warning about delays - that visitors see when they open the website.

## Requirements

- PHP 8.2+
- Statamic 6.35+ (Laravel 12 or 13)

## Installation

```bash
composer require byte5/doormat
php please doormat:install
```

`doormat:install` writes the notices collection (`content/collections/doormat_notices.yaml`) and its
blueprint (`resources/blueprints/collections/doormat_notices/notice.yaml`) into the folders the site
configured for Statamic, with every site listed. Commit both files; an existing file is left alone
unless `--force` is given. The collection appears under Content > Collections and as Tools > Doormat,
for whoever may view it (Statamic's collection permissions).

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

answers `{"data": [...], "meta": {"view": "modal", "delay": 0}}`, each notice with `id`, `version`, `heading`, `type`, `text` (HTML), `button_label`,
`button_url`, `pinned`, `dismissible`, `frequency`; the current site without the parameter, 404 for an
unknown one, never cached. Keep the storage keys of the addon's script if you write your own.

## In the control panel

- **The list** shows each notice's state - Draft, Scheduled, Live or Taken down, worked out from its
  dates - with its type, publish and take-down date and whether it is pinned.
- **Show again to everyone**, an action on a notice (or several), brings it back for the visitors who
  closed it without editing its text. On the origin every language returns; on a localization, that
  language.
- **The settings page** (Tools > Addons > Doormat, or the `edit byte5/doormat settings` permission):
  a master switch that keeps every notice off the site, the presentation (dialog or bars; empty means
  the config file decides) and a delay in seconds before the dialog opens. Saving it flushes the static
  cache. The endpoint hands the presentation and the delay to a site's own script as `meta`.

## Dashboard widget

A "Doormat" card listing, per language, the notices live right now - or "No Doormat notices active"
when none is. It notes when the master switch is off, and shows only to users who may view the
notices. Add it in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'doormat', 'width' => 50],
],
```

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
npm run build         # compile resources/ts to resources/dist/doormat.js - commit the result
```

The tests run on Orchestra Testbench with the Stache, two sites and the addon's own blueprint. GitHub
Actions runs them on PHP 8.2 to 8.5 with Laravel 12 and 13, plus Pint, Larastan and Rector, and
the TypeScript (typecheck, Vitest, and a check that the committed build matches the source), for every
push and pull request. Rector skips the console command attributes, which need Laravel 13.

Releases are made by release-please from the conventional commits on `main`: it keeps a release pull
request with the changelog open; merging it tags the version Composer installs.


## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
