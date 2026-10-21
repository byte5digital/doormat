<?php

namespace Byte5\Doormat\Http\Controllers;

use Byte5\Doormat\NoticeData;
use Byte5\Doormat\Notices;
use Byte5\Doormat\Preview;
use Byte5\Doormat\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;

/**
 * GET /!/doormat/notices?site=<handle>: the active notices of the site (the current
 * one without the parameter) as JSON, fresh on every call - for a site whose own
 * JavaScript shows them. Public content, no authentication.
 *
 * With the token of a notice's live preview (`token=…`, forwarded from the preview
 * page's URL) it answers that notice alone, with the editor's unsaved changes and
 * whatever its dates, state or the master switch say, and `meta.preview` true: the
 * site's script shows it at once and records no dismissal.
 */
class NoticesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $handle = $request->query('site');
        $site = $handle === null ? Site::current() : Site::get((string) $handle);

        abort_if($site === null, 404);

        $preview = Preview::notice($request);

        $notices = match (true) {
            $preview instanceof Entry => [NoticeData::of($preview)],
            ! Settings::enabled() => [],
            default => Notices::active($site->handle())->map(fn (Entry $entry): array => NoticeData::of($entry))->values()->all(),
        };

        $meta = ['view' => Settings::view(), 'delay' => $preview instanceof Entry ? 0 : Settings::delay(), 'preview' => $preview instanceof Entry];

        return response()->json(['data' => $notices, 'meta' => $meta])->header('Cache-Control', 'no-store');
    }
}
