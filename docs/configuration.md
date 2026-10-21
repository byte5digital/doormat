# Configuration

Everything that can be set, and where. Settings are read in this order where they overlap: a tag
parameter, then the settings page, then the config file.

## The config file

Publish it with `php artisan vendor:publish --tag=doormat-config` to `config/doormat.php`.

| Key | Default | What it does |
|-----|---------|--------------|
| `collection` | `doormat_notices` | The collection the notices are entries of. `doormat:install` names its files after it. Change it before installing. |
| `view` | `modal` | How the tag shows the notices a visitor may close: `modal` (a dialog) or `notice` (bars). Applies until the settings page is first saved. |

## The settings page

Control panel: **Tools > Add-ons > Doormat**. Who may open it: users with the permission
`edit byte5/doormat settings` (super users always). Saving it flushes the static cache.

| Field | Values | What it does |
|-------|--------|--------------|
| `enabled` - Show notices | on (default) / off | Off, no notice reaches a visitor on any site, whatever is published. The tag renders nothing and the endpoint answers an empty list. |
| `view` - Presentation | `modal` (Dialog) / `notice` (Bars) | How the notices a visitor may close are shown. Required while `enabled` is on. Notices that cannot be closed are always bars. |
| `wait` - Delay | `no_wait` (default) / `wait` | Whether the dialog opens with the page or waits. |
| `delay` - Seconds | 1-60, default 3 | Shown and checked only while `wait` is `wait`: how many seconds the dialog waits after the page has loaded. |
| `preview_page` - Preview page | a page, or empty | The page a notice's [live preview](frontend.md#live-preview) opens on, in the notice's language. Empty, or where that page is missing or unpublished in the language: the homepage. |

In code: `Byte5\Doormat\Settings::enabled()`, `::view()`, `::delay()` (0 while `no_wait`), `::previewPage()`.

## A notice

A notice is an entry of the collection. The blueprint `doormat:install` writes
(`resources/blueprints/collections/<collection>/notice.yaml`):

| Field | Per language | What it does |
|-------|--------------|--------------|
| `title` - Internal name | no | The entry title: how the notice is listed in the control panel, the same in every language |
| `heading` - Heading | yes | The title visitors see. Required. |
| `text` - Text | yes | The message, Bard: bold, italic, links, lists. Required. |
| `button_link` - Link | yes | Optional: a page of the site or an address. No link, no button. |
| `button_label` - Button text | yes | The button's label; required while a link is set. |
| `notice_type` - Type | no | `info`, `success`, `warning`, `error`: colour and icon. Default `info`. Named `type` before 0.4, which Statamic's entry list cannot show; the update script moves it. |
| `pinned` - Pinned | no | The pinned notice comes first. At most one notice may be pinned (`Byte5\Doormat\Rules\OnePinned`). |
| `dismissible` - Dismissible | no | Default on. Off: the notice is a bar without a close button, until it is taken down. |
| `frequency` - Shows again | no | For a closed notice: `once` (never again, default), `session` (in the next browser session), `always` (on every page). Shown while `dismissible` is on. |
| `date` - Publish date | no | The notice shows from this date and time (a dated collection: a future date keeps it unpublished). Required. |
| `expires_at` - Take-down date | no | Optional: from this date and time the notice is gone. After the publish date. |
| `state` - Status | - | Computed, read-only: `draft`, `scheduled`, `live` or `taken_down`. A column of the list. |

"Per language: no" means one value for every language, set on the notice's original (the site it was
created in). A notice shows on the sites it has a published localization on.

## The collection file

`doormat:install` writes `content/collections/<collection>.yaml`. Besides Statamic's usual keys (dated,
no route, no slugs, every site listed) it carries the two keys live preview needs:

```yaml
entry_class: Byte5\Doormat\Entries\Notice
preview_targets:
  -
    label: 'Page with the notice'
    url: '{doormat_preview_url}'
    refresh: true
```

The entry class extends whatever entry class the site binds - the Stache's, the Eloquent driver's or a
site's own - so the notices stay where they are stored. A site installed before 0.3 adds these keys by
hand (`--force` would overwrite the blueprint too), then runs
`php artisan statamic:stache:clear && php artisan statamic:stache:warm`.

## The tag

`{{ doormat_notices }}` - put it right after `<body>`.

| Parameter | Default | What it does |
|-----------|---------|--------------|
| `view` | the settings page's, else the config's | `modal` or `notice`, for this placement. Anything else is `modal`. |
| `site` | the current site | A site handle: show another site's notices. |

As a pair, `{{ doormat_notices }} … {{ /doormat_notices }}`, it loops the notices with the fields listed
in [Frontend](frontend.md#the-notice-fields). `{{ doormat_notices:script }}` prints the script alone,
`{{ doormat_notices:preview }}` is true on a notice's live preview.

## The endpoint

`GET /!/doormat/notices` - see [Frontend](frontend.md#the-endpoint).

| Query parameter | Default | What it does |
|-----------------|---------|--------------|
| `site` | the current site | A site handle; an unknown one answers 404. |
| `token` | - | Statamic's live-preview token (also read from the `X-Statamic-Token` header): the previewed notice alone, see [Live preview](frontend.md#live-preview). |

## The dashboard widget

Add it in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'doormat', 'width' => 50, 'languages' => 'active'],
],
```

| Option | Default | What it does |
|--------|---------|--------------|
| `type` | - | `doormat` |
| `width` | 100 | Statamic's widget width: 25, 33, 50, 66, 75 or 100 |
| `languages` | `all` | `all`: every language, "No notices active" under those without one. `active`: only the languages that have a live notice. |
| `title` | `Doormat` | The card's title |

It shows only to users who may view the notices collection.

## Commands

| Command | What it does |
|---------|--------------|
| `php please doormat:install [--force]` | Writes the collection and the blueprint into the folders the site configured for Statamic, with every site listed. An existing file is left alone unless `--force`. Run it again with `--force` when a release changed the blueprint. |
| `php please doormat:flush-due` | Flushes the static cache when a publish or take-down time passed within the last minute. Scheduled every minute by the addon; needs a running scheduler. |

## Publish tags

| Tag | What it publishes | When |
|-----|-------------------|------|
| `doormat` | The control panel's script to `public/vendor/doormat/build` | After installing and after every update |
| `doormat-config` | `config/doormat.php` | To change the collection's handle or the default view |
| `doormat-views` | The tag's two views to `resources/views/vendor/doormat` | To restyle or rewrite them |
| `doormat-translations` | `lang/vendor/doormat` | To translate the visitor and control panel strings |

## Permissions

| Permission | Gives |
|------------|-------|
| Statamic's collection permissions (`view doormat_notices entries`, `edit …`, `create …`, `publish …`, `delete …`) | The notices: the list, editing, the "Show again to everyone" action (needs `edit`), the dashboard widget (needs `view`) |
| `edit byte5/doormat settings` | The settings page |

## Translations

The package ships English. A site translates:

- the blueprint's and the settings page's labels, and the action's texts ("Show again to everyone", its
  confirmation and button), in its `lang/<locale>.json`, as for any other blueprint;
- the strings under `doormat::notices` - the dialog's buttons and position text, the state and type names,
  the widget's texts - in `lang/vendor/doormat/<locale>/notices.php`.

## The cache allow-list

Where the application limits the classes a cached value may unserialize into
(`cache.serializable_classes`), the addon adds its entry class there, as Statamic adds its own: live
preview keeps the edited notice in the cache. A site on the Eloquent driver may need the driver's entry
and model classes on the list too, for live preview of any entry.

## Static caching

The tag's output sits in statically cached pages, so the addon keeps the cache current: saving or deleting
a notice, and saving the settings, flush it; `doormat:flush-due` flushes it within a minute of a publish or
take-down time. The endpoint is never cached. A site that shows notices only through the endpoint is not
affected by any of this.
