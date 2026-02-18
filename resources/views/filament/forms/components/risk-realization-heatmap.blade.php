@php
    /**
     * Risk Realization Heatmap
     * - default: abu-abu (seperti menu Scale Map)
     * - marker aktif (Inheren / Residual / Realisasi):
     *   - fill: warna heatmap (c_map)
     *   - border: 3 warna kontras (bisa multi-ring jika overlap)
     * - toggle show/hide marker
     */

    /** @var array $data */
    $d = $data ?? [];

    $colsCount = (int) ($d['colsCount'] ?? 5);
    $rowsCount = (int) ($d['rowsCount'] ?? 5);

    $cols = $d['cols'] ?? [];
    $rows = $d['rows'] ?? [];
    $cells = $d['cells'] ?? [];

    $markers = $d['markers'] ?? [];

    $show = $d['show'] ?? [
        'inherent' => true,
        'residual' => true,
        'realization' => true,
    ];

    $defaultLegend = [
        'inherent' => ['legend' => 'Risiko Inheren', 'border' => '0,230,118'],
        'residual' => ['legend' => 'Risiko Residual', 'border' => '41,121,255'],
        'realization' => ['legend' => 'Realisasi Risiko', 'border' => '255,64,129'],
    ];

    $legend = [
        'inherent' => is_array($markers['inherent'] ?? null) ? $markers['inherent'] : $defaultLegend['inherent'],
        'residual' => is_array($markers['residual'] ?? null) ? $markers['residual'] : $defaultLegend['residual'],
        'realization' => is_array($markers['realization'] ?? null) ? $markers['realization'] : $defaultLegend['realization'],
    ];

    $grayBorder = 'rgb(107,114,128)';
    $grayBg = 'rgba(107,114,128,0.10)';

    /**
     * Ambil marker aktif pada cell sesuai toggle.
     * Urutan inner->outer ring: Realisasi -> Residual -> Inheren.
     */
    $markersAt = function (int $row, int $col) use ($markers, $show): array {
        $order = ['realization', 'residual', 'inherent']; // inner -> outer
        $out = [];

        foreach ($order as $k) {
            if (!($show[$k] ?? true)) {
                continue;
            }

            $m = $markers[$k] ?? null;
            if (!is_array($m)) {
                continue;
            }

            if ((int) ($m['row'] ?? 0) === $row && (int) ($m['col'] ?? 0) === $col) {
                $out[] = $m;
            }
        }

        return $out;
    };
@endphp

<style>
    .rrm-wrap { width: 100%; }

    .rrm-note {
        font-size: 13px;
        color: rgba(229,231,235,0.85);
        margin-bottom: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
    }

    .rrm-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
    }

    .rrm-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: rgba(229,231,235,0.92);
    }

    .rrm-legend-item.is-off { opacity: 0.45; }

    .rrm-legend-box {
        width: 18px;
        height: 18px;
        border-radius: 6px;
        background: rgba(17,24,39,0.55);
        border: 3px solid rgba(156,163,175,0.55);
        box-sizing: border-box;
    }

    .rrm-scroll { overflow-x: hidden; }

    .rrm-layout {
        display: grid;
        grid-template-columns: 56px 1fr;
        gap: 12px;
        align-items: stretch;
        width: 100%;
    }

    .rrm-ybar {
        background: rgba(15, 43, 45, 0.95);
        border: 1px solid rgba(107,114,128,0.35);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 6px;
    }
    .rrm-ybar span {
        display: inline-block;
        color: rgba(229,231,235,0.95);
        font-weight: 800;
        letter-spacing: 1.1px;
        transform: rotate(-90deg);
        white-space: nowrap;
        font-size: 14px;
    }

    .rrm-xbar {
        margin: 0 0 10px 0;
        background: rgba(31,41,55,0.7);
        border: 1px solid rgba(107,114,128,0.35);
        border-radius: 12px;
        padding: 10px 12px;
        text-align: center;
        font-weight: 800;
        color: rgba(229,231,235,0.95);
        letter-spacing: 1px;
    }

    .rrm-grid {
        display: grid;
        grid-template-columns: 180px repeat(var(--rrm-cols), minmax(0, 1fr));
        gap: 10px;
        width: 100%;
        align-items: stretch;
    }

    .rrm-colhead {
        text-align: center;
        font-size: 12px;
        color: rgba(229,231,235,0.92);
        padding: 0 4px;
        line-height: 1.15;
    }
    .rrm-colhead .rrm-colname {
        display: block;
        font-size: 11px;
        color: rgba(156,163,175,0.95);
        margin-top: 2px;
        white-space: normal;
    }

    .rrm-rowhead {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-left: 6px;
        white-space: nowrap;
        overflow: hidden;
    }
    .rrm-rowhead .rrm-rowletter {
        font-weight: 800;
        font-size: 13px;
        color: rgba(229,231,235,0.92);
        width: 18px;
        text-align: left;
        flex: 0 0 auto;
    }
    .rrm-rowhead .rrm-rowname {
        font-size: 12px;
        color: rgba(156,163,175,0.95);
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rrm-cell {
        width: 100%;
        height: 64px;
        border: 2px solid rgb(107,114,128);
        background: rgba(107,114,128,0.10);
        border-radius: 10px;
        text-align: center;
        padding: 8px 6px;
        color: rgba(229,231,235,0.90);
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-sizing: border-box;
    }
    .rrm-cell .rrm-label {
        font-size: 12px;
        font-weight: 800;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rrm-cell .rrm-val {
        font-size: 12px;
        color: rgba(156,163,175,0.95);
        margin-top: 6px;
        line-height: 1;
    }

    @media (max-width: 1200px) {
        .rrm-grid {
            grid-template-columns: 150px repeat(var(--rrm-cols), minmax(0, 1fr));
        }
    }
    @media (max-width: 900px) {
        .rrm-grid {
            grid-template-columns: 120px repeat(var(--rrm-cols), minmax(0, 1fr));
        }
        .rrm-cell { height: 60px; }
        .rrm-cell .rrm-label { font-size: 11px; }
        .rrm-cell .rrm-val { font-size: 11px; }
    }
</style>

<div class="rrm-wrap">
    <div class="rrm-note">
        <div>
            Preview Heat Map: kotak <em>abu-abu</em> = tidak ditampilkan/terpilih,
            kotak <em>berwarna</em> = marker aktif (sesuai toggle).
        </div>

        <div class="rrm-legend">
            @foreach (['inherent', 'residual', 'realization'] as $k)
                @php
                    $it = $legend[$k] ?? $defaultLegend[$k];
                    $border = (string) ($it['border'] ?? '156,163,175');
                    $text = (string) ($it['legend'] ?? '-');
                    $isOn = (bool) ($show[$k] ?? true);
                @endphp
                <div class="rrm-legend-item {{ $isOn ? '' : 'is-off' }}">
                    <span class="rrm-legend-box" style="border-color: rgb({{ $border }});"></span>
                    <span>{{ $text }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rrm-scroll">
        <div class="rrm-xbar">TINGKAT DAMPAK</div>

        <div class="rrm-layout">
            <div class="rrm-ybar"><span>TINGKAT KEMUNGKINAN</span></div>

            <div style="width: 100%;">
                @php $gridStyle = '--rrm-cols:' . $colsCount . ';'; @endphp

                <div class="rrm-grid" style="{{ $gridStyle }}">
                    {{-- Header kolom --}}
                    <div></div>
                    @for ($c = 1; $c <= $colsCount; $c++)
                        @php $col = $cols[$c] ?? ['index' => $c, 'label' => '(' . $c . ')', 'name' => 'Level ' . $c]; @endphp
                        <div class="rrm-colhead">
                            <div style="font-weight: 800;">{{ $col['label'] }}</div>
                            @if (!empty($col['name']))
                                <div class="rrm-colname">{{ $col['name'] }}</div>
                            @endif
                        </div>
                    @endfor

                    {{-- Rows dibalik (E..A) seperti Scale Map --}}
                    @for ($r = $rowsCount; $r >= 1; $r--)
                        @php $row = $rows[$r] ?? ['index' => $r, 'label' => chr(ord('A') + ($r - 1)), 'name' => 'Level ' . $r]; @endphp

                        <div class="rrm-rowhead">
                            <span class="rrm-rowletter">{{ $row['label'] }}</span>
                            <span class="rrm-rowname">{{ $row['name'] ?? '' }}</span>
                        </div>

                        @for ($c = 1; $c <= $colsCount; $c++)
                            @php
                                $cell = $cells[$r][$c] ?? ['value' => ($r * $c), 'label' => '—', 'color' => null];

                                $active = $markersAt($r, $c); // 0..3 (inner->outer)
                                $isMarked = count($active) > 0;

                                $fill = $isMarked ? ((string) ($active[0]['fill'] ?? '')) : '';
                                if ($fill === '' && !empty($cell['color'])) {
                                    $fill = (string) $cell['color'];
                                }

                                $bgColor = ($isMarked && $fill !== '')
                                    ? ('rgba(' . $fill . ', 0.18)')
                                    : $grayBg;

                                $label = (string) ($cell['label'] ?? '—');
                                $val = $cell['value'] ?? null;

                                $borderColor = $grayBorder;
                                $boxShadow = 'none';

                                if ($isMarked) {
                                    $borderColor = 'rgb(' . (string) ($active[0]['border'] ?? '156,163,175') . ')';

                                    $shadows = [];
                                    for ($i = 1; $i < count($active); $i++) {
                                        $spread = 3 * $i;
                                        $rgb = (string) ($active[$i]['border'] ?? '156,163,175');
                                        $shadows[] = '0 0 0 ' . $spread . 'px rgb(' . $rgb . ')';
                                    }
                                    if (!empty($shadows)) {
                                        $boxShadow = implode(', ', $shadows);
                                    }
                                }
                            @endphp

                            <div class="rrm-cell" style="border-color: {{ $borderColor }}; background: {{ $bgColor }}; box-shadow: {{ $boxShadow }};">
                                <div class="rrm-label">{{ $label ?: '—' }}</div>
                                <div class="rrm-val">
                                    @if ($val !== null && $val !== '')
                                        ({{ $val }})
                                    @else
                                        &nbsp;
                                    @endif
                                </div>
                            </div>
                        @endfor
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>
