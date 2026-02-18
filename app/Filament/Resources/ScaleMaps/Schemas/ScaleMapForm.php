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
                '<span style="display:inline-flex;align-items:center;gap:8px;">
                    <span style="display:inline-block;width:14px;height:14px;border-radius:4px;border:1px solid rgba(107,114,128,0.8);background:rgb(' . e($rgb) . ');"></span>
                    <span>' . e($label) . '</span>
                </span>'
            );
        }

        return $out;
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

        foreach ($scales as $scale) {
            $sid = (int) $scale->i_id_scale;
            $groupLabel = self::groupLabelForScale($scale);

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
                            }),

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

                        Select::make('c_map')
                            ->label('Warna (RGB)')
                            ->options(self::colorPaletteHtml())
                            ->allowHtml()
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $mapValue = (int) ($get('i_map') ?? 0);
                                $auto = self::categoryFromMapValue($mapValue)['defaultColor'] ?? null;

                                if ($auto && $state) {
                                    $set('_color_overridden', ((string) $state !== (string) $auto));
                                }
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
        }
    }
}
