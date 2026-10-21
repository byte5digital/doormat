<?php

declare(strict_types=1);

namespace Byte5\Doormat;

use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Tokens\TokenRepository;
use Statamic\CP\LivePreview;
use Statamic\Facades\Blink;
use Statamic\Tokens\Handlers\LivePreview as LivePreviewHandler;

/**
 * The notice an editor is looking at in the control panel's live preview. Statamic loads the preview
 * target with `?token=…` (or the X-Statamic-Token header) and keeps the entry, its unsaved changes as
 * supplements, in the cache under that token. Only a live-preview token of a notice of the collection
 * counts: a missing, expired or unknown token, or one of another entry, is no preview.
 */
final class Preview
{
    public static function notice(?Request $request = null): ?Entry
    {
        $request ??= request();
        $token = $request->input('token') ?? $request->header('X-Statamic-Token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Blink::once("doormat.preview.{$token}", function () use ($token): ?Entry {
            // The repository, not the facade: it answers null for an unknown or expired token, as its contract says.
            $token = resolve(TokenRepository::class)->find($token);

            if ($token === null || $token->handler() !== LivePreviewHandler::class) {
                return null;
            }

            $item = resolve(LivePreview::class)->item($token);

            return $item instanceof Entry && $item->collectionHandle() === Notices::collection() ? $item : null;
        });
    }
}
