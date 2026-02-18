@php
    /** @var array $data */
    $colsCount = (int) ($data['colsCount'] ?? 5);
    $rowsCount = (int) ($data['rowsCount'] ?? 5);

    $cols = $data['cols'] ?? [];
    $rows = $data['rows'] ?? [];

    $selected = $data['selected'] ?? [
        'row' => null,
        'col' => null,
        'value' => null,
        'label' => null,
        'color' => null, // "R,G,B"
    ];

    $cells = $data['cells'] ?? [];

    $selectedRgb = $selected['color'] ? 'rgb(' . $selected['color'] . ')' : '#6B7280';
    $selectedBg  = $selected['color'] ? 'rgba(' . $selected['color'] . ', 0.18)' : 'rgba(107,114,128,0.12)';
@endphp

<style>
    .hm-wrap { width: 100%; }
    .hm-note { font-size: 13px; color: rgba(229,231,235,0.85); margin-bottom: 10px; }

    /* Tidak ada horizontal scroll */
    .hm-scroll { overflow-x: hidden; }

    /* Layout utama: bar kiri + area heatmap */
    .hm-layout {
        display: grid;
        grid-template-columns: 56px 1fr;
        gap: 12px;
        align-items: stretch;
        width: 100%;
    }

    /* Bar kiri: Tingkat Kemungkinan */
    .hm-ybar {
        background: rgba(15, 43, 45, 0.95);
        border: 1px solid rgba(107,114,128,0.35);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 6px;
    }
    .hm-ybar span {
        display: inline-block;
        color: rgba(229,231,235,0.95);
        font-weight: 800;
        letter-spacing: 1.1px;
        transform: rotate(-90deg);
        white-space: nowrap;
        font-size: 14px;
    }

    /* Bar atas: Tingkat Dampak */
    .hm-xbar {
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

    /* Grid berbasis CSS Grid (lebih responsif daripada table) */
    .hm-grid {
        display: grid;
        /* 1 kolom label baris + N kolom cell */
        grid-template-columns: 180px repeat(var(--hm-cols), minmax(0, 1fr));
        gap: 10px;
        width: 100%;
        align-items: stretch;
    }

    /* Header kolom (1..N) */
    .hm-colhead {
        text-align: center;
        font-size: 12px;
        color: rgba(229,231,235,0.92);
        padding: 0 4px;
        line-height: 1.15;
    }
    .hm-colhead .hm-colname {
        display: block;
        font-size: 11px;
        color: rgba(156,163,175,0.95);
        margin-top: 2px;
        white-space: normal;
    }

    /* Label baris kiri (A-E + nama) -> lebih sempit & rata kiri */
    .hm-rowhead {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-left: 6px;
        white-space: nowrap;
        overflow: hidden;
    }
    .hm-rowhead .hm-rowletter {
        font-weight: 800;
        font-size: 13px;
        color: rgba(229,231,235,0.92);
        width: 18px;
        text-align: left;
        flex: 0 0 auto;
    }
    .hm-rowhead .hm-rowname {
        font-size: 12px;
        color: rgba(156,163,175,0.95);
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Cell: fleksibel mengikuti lebar layar */
    .hm-cell {
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
    .hm-cell .hm-label {
        font-size: 12px;
        font-weight: 800;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .hm-cell .hm-val {
        font-size: 12px;
        color: rgba(156,163,175,0.95);
        margin-top: 6px;
        line-height: 1;
    }

   
    @media (max-width: 1200px) {
        .hm-grid {
            grid-template-columns: 150px repeat(var(--hm-cols), minmax(0, 1fr));
        }
    }
    @media (max-width: 900px) {
        .hm-grid {
            grid-template-columns: 120px repeat(var(--hm-cols), minmax(0, 1fr));
        }
        .hm-cell { height: 60px; }
        .hm-cell .hm-label { font-size: 11px; }
        .hm-cell .hm-val { font-size: 11px; }
    }
</style>

<div class="hm-wrap">
    <div class="hm-note">
        Preview Heat Map (kotak abu-abu adalah template, kotak berwarna = koordinat terpilih).
    </div>

    <div class="hm-scroll">
        
        <div class="hm-xbar">TINGKAT DAMPAK</div>

        <div class="hm-layout">
            {{-- Bar kiri: Tingkat Kemungkinan --}}
            <div class="hm-ybar">
                <span>TINGKAT KEMUNGKINAN</span>
            </div>

            {{-- Area grid --}}
            <div style="width: 100%;">
                @php
                    // set CSS var untuk jumlah kolom
                    $gridStyle = '--hm-cols:' . $colsCount . ';';
                @endphp

                <div class="hm-grid" style="{{ $gridStyle }}">
                    {{-- Row 0: header kolom --}}
                    <div></div>
                    @for ($c = 1; $c <= $colsCount; $c++)
                        @php
                            $col = $cols[$c] ?? ['index' => $c, 'label' => '(' . $c . ')', 'name' => 'Level ' . $c];
                        @endphp
                        <div class="hm-colhead">
                            <div style="font-weight: 800;">{{ $col['label'] }}</div>
                            @if (!empty($col['name']))
                                <div class="hm-colname">{{ $col['name'] }}</div>
                            @endif
                        </div>
                    @endfor

                   
                    @for ($r = $rowsCount; $r >= 1; $r--)
                        @php
                            $row = $rows[$r] ?? ['index' => $r, 'label' => chr(ord('A') + ($r - 1)), 'name' => 'Level ' . $r];
                        @endphp

                        {{-- row head --}}
                        <div class="hm-rowhead">
                            <span class="hm-rowletter">{{ $row['label'] }}</span>
                            <span class="hm-rowname">{{ $row['name'] ?? '' }}</span>
                        </div>

                        {{-- cells --}}
                        @for ($c = 1; $c <= $colsCount; $c++)
                            @php
                                $cell = $cells[$r][$c] ?? ['value' => null, 'label' => '—'];

                                $isSelected = ($selected['row'] === $r && $selected['col'] === $c);

                                $borderColor = $isSelected ? $selectedRgb : 'rgb(107,114,128)';
                                $bgColor = $isSelected ? $selectedBg : 'rgba(107,114,128,0.10)';

                                $label = $isSelected
                                    ? ($selected['label'] ?? $cell['label'])
                                    : ($cell['label'] ?? '—');

                                $val = $isSelected
                                    ? ($selected['value'] ?? $cell['value'])
                                    : ($cell['value'] ?? null);
                            @endphp

                            <div class="hm-cell" style="border-color: {{ $borderColor }}; background: {{ $bgColor }};">
                                <div class="hm-label">{{ $label ?: '—' }}</div>
                                <div class="hm-val">
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
