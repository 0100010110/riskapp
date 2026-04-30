<?php

namespace App\Filament\Resources\ScaleMaps\Schemas;

use App\Filament\Resources\ScaleMaps\ScaleMapResource;
use App\Models\Trscale;
use App\Models\Trscaledetail;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ScaleMapForm
{
    private const CUSTOM_COLOR_KEY = '__custom__';

    public static function categoryFromMapValue(?int $value): array
    {
        if (! $value) {
            return ['label' => null, 'defaultColor' => null];
        }

        if ($value >= 20) return ['label' => 'High', 'defaultColor' => '244,67,54'];
        if ($value >= 15) return ['label' => 'Moderate to High', 'defaultColor' => '255,152,0'];
        if ($value >= 12) return ['label' => 'Moderate', 'defaultColor' => '255,235,59'];
        if ($value >= 6)  return ['label' => 'Low to Moderate', 'defaultColor' => '76,175,80'];

        return ['label' => 'Low', 'defaultColor' => '63,81,181'];
    }

    public static function colorPalette(): array
    {
        return [
            '63,81,181'   => 'Low (Blue) - 63,81,181',
            '76,175,80'   => 'Low to Moderate (Green) - 76,175,80',
            '255,235,59'  => 'Moderate (Yellow) - 255,235,59',
            '255,152,0'   => 'Moderate to High (Orange) - 255,152,0',
            '244,67,54'   => 'High (Red) - 244,67,54',
            '0,188,212'   => 'Cyan - 0,188,212',
            '156,39,176'  => 'Purple - 156,39,176',
            '233,30,99'   => 'Magenta - 233,30,99',
            '121,85,72'   => 'Brown - 121,85,72',
            '96,125,139'  => 'Blue Grey - 96,125,139',
        ];
    }

    public static function colorPaletteHtml(): array
    {
        $out = [];

        foreach (self::colorPalette() as $rgb => $label) {
            $out[$rgb] = (string) new HtmlString(
                '<span style="display:inline-flex;align-items:center;gap:8px;">'
                . '<span style="display:inline-block;width:14px;height:14px;border-radius:4px;border:1px solid rgba(107,114,128,0.8);background:rgb(' . e($rgb) . ');"></span>'
                . '<span>' . e($label) . '</span>'
                . '</span>'
            );
        }

        return $out;
    }

    public static function colorOptionsHtmlWithCustom(?string $customRgb = null): array
    {
        $out = self::colorPaletteHtml();

        $swatchRgb = self::isValidRgbString($customRgb) ? (string) $customRgb : '156,163,175';
        $hexLabel  = self::rgbToHex($customRgb) ?: '#RRGGBB';

        $out[self::CUSTOM_COLOR_KEY] = (string) new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:8px;">'
            . '<span style="display:inline-block;width:14px;height:14px;border-radius:4px;border:1px solid rgba(107,114,128,0.8);background:rgb(' . e($swatchRgb) . ');"></span>'
            . '<span>Custom - ' . e($hexLabel) . '</span>'
            . '</span>'
        );

        return $out;
    }

    private static function isValidRgbString(?string $rgb): bool
    {
        if (! $rgb) return false;

        return (bool) preg_match(
            '/^\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*$/',
            (string) $rgb,
            $m
        )
            && ((int) $m[1]) >= 0 && ((int) $m[1]) <= 255
            && ((int) $m[2]) >= 0 && ((int) $m[2]) <= 255
            && ((int) $m[3]) >= 0 && ((int) $m[3]) <= 255;
    }

    private static function normalizeHexColor(?string $hex): ?string
    {
        $hex = strtoupper(trim((string) $hex));

        if ($hex === '') {
            return null;
        }

        if ($hex[0] !== '#') {
            $hex = '#' . $hex;
        }

        if (! preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $hex)) {
            return null;
        }

        return $hex;
    }

    public static function hexToRgb(?string $hex): ?string
    {
        $hex = self::normalizeHexColor($hex);
        if (! $hex) return null;

        $h = substr($hex, 1);

        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }

        $r = hexdec(substr($h, 0, 2));
        $g = hexdec(substr($h, 2, 2));
        $b = hexdec(substr($h, 4, 2));

        return $r . ',' . $g . ',' . $b;
    }

    public static function rgbToHex(?string $rgb): ?string
    {
        if (! self::isValidRgbString($rgb)) {
            return null;
        }

        preg_match('/^\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*$/', (string) $rgb, $m);

        $r = max(0, min(255, (int) $m[1]));
        $g = max(0, min(255, (int) $m[2]));
        $b = max(0, min(255, (int) $m[3]));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private static function isPaletteColor(?string $rgb): bool
    {
        if (! $rgb) return false;
        return array_key_exists((string) $rgb, self::colorPalette());
    }

    private static function unitByScaleType(string $scaleType): string
    {
        return $scaleType === '1' ? '$' : ($scaleType === '2' ? '%' : '');
    }

    private static function operatorLabel(?string $op): string
    {
        $op = trim((string) $op);

        $map = [];
        if (defined(Trscaledetail::class . '::OPERATORS')) {
            $map = Trscaledetail::OPERATORS;
        }

        if (is_array($map) && array_key_exists($op, $map)) {
            return trim((string) $map[$op]);
        }

        return $op !== '' ? $op : '-';
    }

    private static function groupLabelForScale(Trscale $scale): string
    {
        $code = trim((string) ($scale->v_scale ?? ''));
        if ($code === '') {
            $code = (string) ((int) ($scale->i_scale ?? 0));
        }

        $nilai = (string) ((int) ($scale->i_scale ?? 0));
        $ass   = trim((string) ($scale->n_scale_assumption ?? ''));
        if ($ass === '') $ass = '-';

        return "{$code} | {$nilai} | {$ass}";
    }

    private static function itemLabelForDetail(Trscaledetail $detail, string $scaleType): string
    {
        $unit = self::unitByScaleType($scaleType);

        $score = (int) ($detail->i_detail_score ?? 0);
        $op    = self::operatorLabel($detail->v_detail ?? null);

        $bound = trim((string) ($detail->c_detail ?? ''));
        if ($bound === '') $bound = '-';

        return "{$score} | {$op} {$bound}{$unit}";
    }

    private static function flatLabelForDetail(Trscaledetail $detail): string
    {
        $scaleType = (string) ($detail->scale?->c_scale_type ?? '');
        $group = $detail->scale ? self::groupLabelForScale($detail->scale) : '-';
        $item  = self::itemLabelForDetail($detail, $scaleType !== '' ? $scaleType : '1');

        return "{$group} — {$item}";
    }

    private static function groupedDetailOptions(string $scaleType, ?string $search = null, ?int $limitScales = null): array
    {
        $search = trim((string) $search);

        $scalesQ = Trscale::query()
            ->where('c_scale_type', $scaleType)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where(function ($w) use ($like) {
                    $w->where('v_scale', 'ilike', $like)
                        ->orWhere('n_scale_assumption', 'ilike', $like)
                        ->orWhereRaw('CAST(i_scale AS TEXT) ILIKE ?', [$like]);
                });
            })
            ->orderBy('i_scale');

        if ($limitScales !== null && $limitScales > 0) {
            $scalesQ->limit($limitScales);
        }

        $scales = $scalesQ->get();

        if ($scales->isEmpty()) {
            return [];
        }

        $scaleIds = $scales->pluck('i_id_scale')->map(fn ($v) => (int) $v)->all();

        $details = Trscaledetail::query()
            ->with(['scale'])
            ->whereIn('i_id_scale', $scaleIds)
            ->orderBy('i_id_scale')
            ->orderBy('i_detail_score')
            ->get();

        $detailsByScale = $details->groupBy(fn (Trscaledetail $d) => (int) ($d->i_id_scale ?? 0));

        $out = [];

        foreach ($scales as $scaleRow) {
            $scaleModel = $scaleRow instanceof Trscale
                ? $scaleRow
                : (new Trscale())->forceFill((array) $scaleRow);

            $sid = (int) ($scaleModel->i_id_scale ?? 0);
            if ($sid <= 0) {
                continue;
            }

            $groupLabel = self::groupLabelForScale($scaleModel);

            /** @var \Illuminate\Support\Collection<int,Trscaledetail> $rows */
            $rows = $detailsByScale->get($sid, collect());

            if ($rows->isEmpty()) {
                continue;
            }

            foreach ($rows as $d) {
                $out[$groupLabel][(int) $d->getKey()] = self::itemLabelForDetail($d, $scaleType);
            }
        }

        return $out;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 1])
            ->schema([
                Section::make('Heat Map (Skala Map)')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('_color_overridden')
                            ->default(false)
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Set $set, Get $get) {
                                $mapValue = (int) ($get('i_map') ?? 0);
                                $auto = self::categoryFromMapValue($mapValue)['defaultColor'] ?? null;
                                $current = $get('c_map');

                                $set('_color_overridden', $current && $auto && $current !== $auto);

                                if ($current) {
                                    if (self::isPaletteColor((string) $current)) {
                                        $set('c_map_choice', (string) $current);
                                        $set('c_map_custom_hex', null);
                                    } else {
                                        $set('c_map_choice', self::CUSTOM_COLOR_KEY);
                                        $set('c_map_custom_hex', self::rgbToHex((string) $current));
                                    }
                                } elseif ($auto) {
                                    $set('c_map_choice', (string) $auto);
                                    $set('c_map', (string) $auto);
                                    $set('c_map_custom_hex', null);
                                }
                            }),

                        Hidden::make('c_map')
                            ->required(),

                        ViewField::make('heatmap_preview')
                            ->label('')
                            ->view('filament.forms.components.scale-map-preview')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->viewData(function (Get $get) {
                                $colsCount = 5;
                                $rowsCount = 5;

                                $maxImpact = Trscaledetail::query()
                                    ->whereHas('scale', fn ($q) => $q->where('c_scale_type', '1'))
                                    ->max('i_detail_score');

                                $maxLikelihood = Trscaledetail::query()
                                    ->whereHas('scale', fn ($q) => $q->where('c_scale_type', '2'))
                                    ->max('i_detail_score');

                                $colsCount = max(5, (int) ($maxImpact ?: 0));
                                $rowsCount = max(5, (int) ($maxLikelihood ?: 0));

                                $impactNames = [
                                    1 => 'Sangat Rendah',
                                    2 => 'Rendah',
                                    3 => 'Moderate',
                                    4 => 'Tinggi',
                                    5 => 'Sangat Tinggi',
                                ];
                                $likelihoodNames = [
                                    1 => 'Sangat Jarang',
                                    2 => 'Jarang',
                                    3 => 'Bisa',
                                    4 => 'Sangat Mungkin',
                                    5 => 'Hampir Pasti',
                                ];

                                $cols = [];
                                for ($c = 1; $c <= $colsCount; $c++) {
                                    $cols[$c] = [
                                        'index' => $c,
                                        'label' => '(' . $c . ')',
                                        'name' => $impactNames[$c] ?? ('Level ' . $c),
                                    ];
                                }

                                $rows = [];
                                for ($r = 1; $r <= $rowsCount; $r++) {
                                    $letter = chr(ord('A') + ($r - 1));
                                    $rows[$r] = [
                                        'index' => $r,
                                        'label' => $letter,
                                        'name' => $likelihoodNames[$r] ?? ('Level ' . $r),
                                    ];
                                }

                                $selectedRow = null;
                                $selectedCol = null;

                                $aId = $get('i_id_scale_a');
                                $bId = $get('i_id_scale_b');

                                if ($aId) {
                                    $selectedCol = (int) (Trscaledetail::query()->find((int) $aId)?->i_detail_score ?: 0) ?: null;
                                }
                                if ($bId) {
                                    $selectedRow = (int) (Trscaledetail::query()->find((int) $bId)?->i_detail_score ?: 0) ?: null;
                                }

                                $selectedValue = $get('i_map');
                                $selectedValue = is_numeric($selectedValue) ? (int) $selectedValue : null;

                                $cat = self::categoryFromMapValue($selectedValue);
                                $autoColor = $cat['defaultColor'] ?? null;
                                $chosenColor = $get('c_map') ?: $autoColor;

                                $cells = [];
                                for ($r = 1; $r <= $rowsCount; $r++) {
                                    for ($c = 1; $c <= $colsCount; $c++) {
                                        $templateValue = $r * $c;
                                        $cellCat = self::categoryFromMapValue($templateValue);
                                        $cells[$r][$c] = [
                                            'value' => $templateValue,
                                            'label' => $cellCat['label'] ?? '—',
                                        ];
                                    }
                                }

                                return [
                                    'data' => [
                                        'colsCount' => $colsCount,
                                        'rowsCount' => $rowsCount,
                                        'cols' => $cols,
                                        'rows' => $rows,
                                        'cells' => $cells,
                                        'selected' => [
                                            'row' => $selectedRow,
                                            'col' => $selectedCol,
                                            'value' => $selectedValue,
                                            'label' => $cat['label'] ?? null,
                                            'color' => $chosenColor,
                                        ],
                                    ],
                                ];
                            }),

                        Select::make('i_id_scale_a')
                            ->label('Detail Skala A (Dampak)')
                            ->native(false)
                            ->searchable()
                            ->placeholder('Cari kode / nilai / asumsi...')
                            ->options(fn () => self::groupedDetailOptions('1', null, 25))
                            ->getSearchResultsUsing(fn (string $search) => self::groupedDetailOptions('1', $search, 50))
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (! $value) return null;

                                $detail = Trscaledetail::query()
                                    ->with(['scale'])
                                    ->find((int) $value);

                                return $detail ? self::flatLabelForDetail($detail) : (string) $value;
                            })
                            ->preload(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncMapValueAndColor($set, $get)),

                        Select::make('i_id_scale_b')
                            ->label('Detail Skala B (Kemungkinan)')
                            ->native(false)
                            ->searchable()
                            ->placeholder('Cari kode / nilai / asumsi...')
                            ->options(fn () => self::groupedDetailOptions('2', null, 25))
                            ->getSearchResultsUsing(fn (string $search) => self::groupedDetailOptions('2', $search, 50))
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (! $value) return null;

                                $detail = Trscaledetail::query()
                                    ->with(['scale'])
                                    ->find((int) $value);

                                return $detail ? self::flatLabelForDetail($detail) : (string) $value;
                            })
                            ->preload(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncMapValueAndColor($set, $get))
                            ->different('i_id_scale_a'),

                        TextInput::make('i_map')
                            ->label('Nilai Map (A x B)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->required()
                            ->helperText('Otomatis: Skor(A) x Skor(B).'),

                        Select::make('c_map_choice')
                            ->label('Warna (RGB)')
                            ->options(fn (Get $get) => self::colorOptionsHtmlWithCustom((string) ($get('c_map') ?? '')))
                            ->allowHtml()
                            ->searchable()
                            ->required()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if (! $state) {
                                    return;
                                }

                                $mapValue = (int) ($get('i_map') ?? 0);
                                $auto = self::categoryFromMapValue($mapValue)['defaultColor'] ?? null;

                                if ((string) $state === self::CUSTOM_COLOR_KEY) {
                                    $hex = trim((string) ($get('c_map_custom_hex') ?? ''));

                                    if ($hex === '') {
                                        $baseRgb = (string) ($get('c_map') ?: ($auto ?: '156,163,175'));
                                        $hex = self::rgbToHex($baseRgb) ?: '';
                                        if ($hex !== '') {
                                            $set('c_map_custom_hex', $hex);
                                        }
                                    }

                                    $rgb = self::hexToRgb($hex);
                                    if ($rgb) {
                                        $set('c_map', $rgb);
                                        $set('_color_overridden', $auto ? ((string) $rgb !== (string) $auto) : true);
                                    }

                                    return;
                                }

                                $set('c_map', (string) $state);
                                $set('c_map_custom_hex', null);
                                $set('_color_overridden', $auto ? ((string) $state !== (string) $auto) : true);
                            }),

                        
                        TextInput::make('c_map_custom_hex')
                            ->label('Custom (Hex)')
                            ->placeholder('#FFFFFF')
                            ->helperText('Masukkan kode hex: #RGB atau #RRGGBB. Contoh: #FFFFFF')
                            ->dehydrated(false)
                            ->visible(fn (Get $get) => (string) ($get('c_map_choice') ?? '') === self::CUSTOM_COLOR_KEY)
                            ->required(fn (Get $get) => (string) ($get('c_map_choice') ?? '') === self::CUSTOM_COLOR_KEY)
                            ->rule('regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $normalized = self::normalizeHexColor((string) $state);
                                if ($normalized) {
                                    $set('c_map_custom_hex', $normalized);
                                }

                                $rgb = self::hexToRgb((string) ($normalized ?: $state));
                                if (! $rgb) {
                                    return;
                                }

                                $set('c_map', $rgb);

                                $mapValue = (int) ($get('i_map') ?? 0);
                                $auto = self::categoryFromMapValue($mapValue)['defaultColor'] ?? null;

                                $set('_color_overridden', $auto ? ((string) $rgb !== (string) $auto) : true);
                            }),

                        ViewField::make('c_map_custom_preview')
                            ->label('') // no label, biar clean di kolom kanan
                            ->view('filament.forms.components.color-preview-swatch')
                            ->dehydrated(false)
                            ->visible(fn (Get $get) => (string) ($get('c_map_choice') ?? '') === self::CUSTOM_COLOR_KEY)
                            ->viewData(function (Get $get) {
                                $rgb = (string) ($get('c_map') ?? '');
                                return [
                                    'data' => [
                                        'rgb' => $rgb !== '' ? $rgb : null,
                                        'hex' => self::rgbToHex($rgb),
                                        // turunkan isi preview supaya sejajar dengan field input (bukan sejajar label)
                                        'offset' => true,
                                    ],
                                ];
                            }),

                        Textarea::make('n_map')
                            ->label('Penjelasan')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function syncMapValueAndColor(Set $set, Get $get): void
    {
        $aId = $get('i_id_scale_a');
        $bId = $get('i_id_scale_b');

        $mapValue = ScaleMapResource::computeMapValue(
            $aId ? (int) $aId : null,
            $bId ? (int) $bId : null,
        );

        $set('i_map', $mapValue);

        $overridden = (bool) ($get('_color_overridden') ?? false);

        $cat = self::categoryFromMapValue(is_numeric($mapValue) ? (int) $mapValue : null);
        $autoColor = $cat['defaultColor'] ?? null;

        if (! $overridden && $autoColor) {
            $set('c_map', $autoColor);
            $set('c_map_choice', $autoColor);
            $set('c_map_custom_hex', null);
        }
    }
}