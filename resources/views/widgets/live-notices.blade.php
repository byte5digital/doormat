{{-- The dashboard's Doormat card (Byte5\Doormat\Widgets\LiveNotices): the live notices per language. --}}
<div style="padding: 1rem 1.25rem; border: 1px solid rgba(127, 127, 127, .25); border-radius: .5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem;">
        <h2 style="margin: 0; font-size: 1rem; font-weight: 600;">{{ __($title) }}</h2>
        <a href="{{ $listUrl }}" style="font-size: .875rem;">{{ __('doormat::notices.widget.all') }}</a>
    </div>

    @unless ($enabled)
        <p data-doormat-widget-off style="margin: 0 0 .75rem; padding: .5rem .75rem; border-radius: .375rem; background: rgba(217, 119, 6, .12); font-size: .875rem;">
            {{ __('doormat::notices.widget.switched_off') }}
        </p>
    @endunless

    @if (! $any)
        <p data-doormat-widget-empty style="margin: 0; font-size: .875rem; opacity: .7;">{{ __('doormat::notices.widget.none') }}</p>
    @else
        @foreach ($sites as $site)
            <div style="margin-top: .75rem;">
                <h3 style="margin: 0 0 .25rem; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; opacity: .7;">{{ $site['name'] }}</h3>
                @if ($site['notices'] === [])
                    <p style="margin: 0; font-size: .875rem; opacity: .7;">{{ __('doormat::notices.widget.none_here') }}</p>
                @else
                    <ul style="margin: 0; padding: 0; list-style: none;">
                        @foreach ($site['notices'] as $notice)
                            <li style="display: flex; align-items: baseline; gap: .5rem; padding: .25rem 0; font-size: .875rem;">
                                <a href="{{ $notice['url'] }}">{{ $notice['title'] }}</a>
                                <span style="opacity: .7;">{{ $notice['type'] }}</span>
                                @if ($notice['pinned'])
                                    <span style="opacity: .7;">· {{ __('doormat::notices.widget.pinned') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif
</div>
