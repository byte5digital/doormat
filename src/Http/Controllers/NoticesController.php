<?php

namespace Byte5\Doormat\Http\Controllers;

use Byte5\Doormat\NoticeData;
use Byte5\Doormat\Notices;
use Byte5\Doormat\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;

/**
 * GET /!/doormat/notices?site=<handle>: the active notices of the site (the current
 * one without the parameter) as JSON, fresh on every call - for a site whose own
 * JavaScript shows them. Public content, no authentication.
 */
class NoticesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $handle = $request->query('site');
        $site = $handle === null ? Site::current() : Site::get((string) $handle);

        abort_if($site === null, 404);

        $notices = ! Settings::enabled() ? [] : Notices::active($site->handle())->map(fn (Entry $entry): array => NoticeData::of($entry))->values()->all();

        return response()->json(['data' => $notices, 'meta' => ['view' => Settings::view(), 'delay' => Settings::delay()]])->header('Cache-Control', 'no-store');
    }
}
