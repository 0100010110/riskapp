@php
    
    $rgb = $data['rgb'] ?? null;
    $hex = $data['hex'] ?? null;
    $offset = (bool) ($data['offset'] ?? false);

    $bg = $rgb ? ('rgb(' . $rgb . ')') : 'rgba(107,114,128,0.25)';

    $wrapPadTop = $offset ? 'padding-top: 1.65rem;' : '';
@endphp

<div style="{{ $wrapPadTop }}">
    <div
        style="
            display:flex;
            align-items:center;
            gap:12px;
            min-height: 44px;
            width: 100%;
        "
    >
        <span
            style="
                flex: 0 0 auto;
                display:inline-block;
                width:44px;
                height:44px;
                border-radius:12px;
                border:2px solid rgba(107,114,128,0.8);
                background: {{ $bg }};
                box-sizing: border-box;
            "
            title="{{ $hex ?? ($rgb ? ('rgb(' . $rgb . ')') : '') }}"
        ></span>

        <div style="line-height: 1.1; overflow:hidden;">
            <div
                style="
                    font-size: 12px;
                    font-weight: 700;
                    color: rgba(229,231,235,0.92);
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                "
            >
                {{ $hex ?? '-' }}
            </div>

            @if ($rgb)
                <div
                    style="
                        font-size: 12px;
                        color: rgba(156,163,175,0.95);
                        margin-top: 4px;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    "
                >
                    rgb({{ $rgb }})
                </div>
            @endif
        </div>
    </div>
</div>