<?php

namespace App\Filament\Resources\RiskRealizations\Schemas;

use App\Filament\Resources\ScaleMaps\Schemas\ScaleMapForm;
use App\Models\Tmriskinherent;
use App\Models\Tmriskrealization;
use App\Models\Tmtaxonomyscale;
use App\Models\Trscalemap;
use App\Models\Trscale;
use App\Models\Trscaledetail;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RiskRealizationForm
{
    public const BORDER_INHERENT    = '0,230,118';
    public const BORDER_RESIDUAL    = '41,121,255';
    public const BORDER_REALIZATION = '255,64,129';

    private const PERIOD_TYPES = [
        'bulan'    => 'Bulan',
        'triwulan' => 'Triwulan',
        'semester' => 'Semester',
    ];

    protected static ?array $cachedAccess = null;

    /**
     * @return array{can_all:bool, org_prefix:string}
     */
    protected static function accessContext(): array
    {
        if (static::$cachedAccess !== null) {
            return static::$cachedAccess;
        }

        $ctx = RiskApprovalWorkflow::context();

        $orgPrefix = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));
        $roleType  = (string) ($ctx['role_type'] ?? '');
        $isSuper   = (bool) ($ctx['is_superadmin'] ?? false);

        $canAll = $isSuper
            || in_array($roleType, [
                RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
                RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
            ], true);

        return static::$cachedAccess = [
            'can_all'    => $canAll,
            'org_prefix' => $orgPrefix,
        ];
    }

    private static function yearRange(): array
    {
        $nowYear   = (int) now()->format('Y');
        $startYear = $nowYear;
        $endYear   = $nowYear + 10;

        $out = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $out[(string) $y] = (string) $y;
        }

        return $out;
    }

    private static function monthOptions(): array
    {
        return [
            'M01' => 'Jan', 'M02' => 'Feb', 'M03' => 'Mar', 'M04' => 'Apr',
            'M05' => 'Mei', 'M06' => 'Jun', 'M07' => 'Jul', 'M08' => 'Agu',
            'M09' => 'Sep', 'M10' => 'Okt', 'M11' => 'Nov', 'M12' => 'Des',
        ];
    }

    private static function triwulanOptions(): array
    {
        return ['1' => 'TW 1', '2' => 'TW 2', '3' => 'TW 3', '4' => 'TW 4'];
    }

    private static function semesterOptions(): array
    {
        return ['S1' => 'Sem 1', 'S2' => 'Sem 2'];
    }

    private static function defaultTermForType(?string $type): ?string
    {
        return match ($type) {
            'bulan'    => 'M01',
            'semester' => 'S1',
            'triwulan' => '1',
            default    => null,
        };
    }

    private static function termOptionsForType(?string $type): array
    {
        return match ($type) {
            'bulan'    => self::monthOptions(),
            'semester' => self::semesterOptions(),
            'triwulan' => self::triwulanOptions(),
            default    => [],
        };
    }

 
    private static function parseRealizationPeriod(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            $y = (string) now()->format('Y');
            return ['type' => 'triwulan', 'year' => $y, 'term' => '1'];
        }

        $normalized = str_replace(' - ', '-', $value);
        $parts = array_map('trim', explode('-', $normalized));

        $year = $parts[0] ?? (string) now()->format('Y');
        $term = $parts[1] ?? '1';

        $termUpper = strtoupper($term);

        if (str_starts_with($termUpper, 'M')) {
            return ['type' => 'bulan', 'year' => $year, 'term' => $termUpper];
        }

        if (in_array($termUpper, ['S1', 'S2'], true)) {
            return ['type' => 'semester', 'year' => $year, 'term' => $termUpper];
        }

        if (ctype_digit($term) && (int) $term >= 1 && (int) $term <= 4) {
            return ['type' => 'triwulan', 'year' => $year, 'term' => (string) (int) $term];
        }

        return ['type' => 'triwulan', 'year' => $year, 'term' => '1'];
    }

    private static function syncRealizationPeriod(Set $set, Get $get): void
    {
        $type = (string) ($get('_realization_period_type') ?? '');
        $year = (string) ($get('_realization_year') ?? '');
        $term = (string) ($get('_realization_term') ?? '');

        if ($type === '' || $year === '' || $term === '') {
            $set('c_realization_period', '');
            return;
        }

        $allowed = array_keys(self::termOptionsForType($type));
        if (! in_array($term, $allowed, true)) {
            $term = self::defaultTermForType($type) ?? '';
            $set('_realization_term', $term);
        }

        if ($term === '') {
            $set('c_realization_period', '');
            return;
        }

        $set('c_realization_period', "{$year} - {$term}");
    }

    /**
     * @return array{
     *   taxonomyId:int|null,
     *   impactScaleIds:array<int>,
     *   likelihoodScaleIds:array<int>,
     *   impactCodes:array<string>,
     *   likelihoodCodes:array<string>
     * }
     */
    private static function scaleContextForRiskInherent(?int $riskInherentId): array
    {
        $riskInherentId = (int) ($riskInherentId ?: 0);

        if ($riskInherentId <= 0) {
            return [
                'taxonomyId' => null,
                'impactScaleIds' => [],
                'likelihoodScaleIds' => [],
                'impactCodes' => [],
                'likelihoodCodes' => [],
            ];
        }

        $ri = Tmriskinherent::query()
            ->with(['risk' => fn ($q) => $q->select(['i_id_risk', 'i_id_taxonomy'])])
            ->select(['i_id_riskinherent', 'i_id_risk'])
            ->find($riskInherentId);

        $taxonomyId = (int) ($ri?->risk?->i_id_taxonomy ?? 0);
        if ($taxonomyId <= 0) {
            return [
                'taxonomyId' => null,
                'impactScaleIds' => [],
                'likelihoodScaleIds' => [],
                'impactCodes' => [],
                'likelihoodCodes' => [],
            ];
        }

        $scaleIds = Tmtaxonomyscale::query()
            ->where('i_id_taxonomy', $taxonomyId)
            ->pluck('i_id_scale')
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($scaleIds)) {
            return [
                'taxonomyId' => $taxonomyId,
                'impactScaleIds' => [],
                'likelihoodScaleIds' => [],
                'impactCodes' => [],
                'likelihoodCodes' => [],
            ];
        }

        $scales = Trscale::query()
            ->whereIn('i_id_scale', $scaleIds)
            ->get(['i_id_scale', 'c_scale_type', 'v_scale']);

        $impactScaleIds = $scales
            ->filter(fn (Trscale $s) => (string) ($s->c_scale_type ?? '') === '1')
            ->pluck('i_id_scale')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $likelihoodScaleIds = $scales
            ->filter(fn (Trscale $s) => (string) ($s->c_scale_type ?? '') === '2')
            ->pluck('i_id_scale')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $impactCodes = $scales
            ->filter(fn (Trscale $s) => (string) ($s->c_scale_type ?? '') === '1')
            ->map(fn (Trscale $s) => trim((string) ($s->v_scale ?? '')))
            ->filter(fn (string $v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        $likelihoodCodes = $scales
            ->filter(fn (Trscale $s) => (string) ($s->c_scale_type ?? '') === '2')
            ->map(fn (Trscale $s) => trim((string) ($s->v_scale ?? '')))
            ->filter(fn (string $v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'taxonomyId' => $taxonomyId,
            'impactScaleIds' => $impactScaleIds,
            'likelihoodScaleIds' => $likelihoodScaleIds,
            'impactCodes' => $impactCodes,
            'likelihoodCodes' => $likelihoodCodes,
        ];
    }

    private static function helperTextForRiskInherent(?int $riskInherentId): string
    {
        $ctx = self::scaleContextForRiskInherent($riskInherentId);

        if ($ctx['taxonomyId'] === null) {
            return 'Pilih Risk Inherent terlebih dahulu.';
        }

        if (empty($ctx['impactScaleIds']) || empty($ctx['likelihoodScaleIds'])) {
            return 'Skala pada taxonomy Risk belum lengkap. Wajib ada minimal 1 skala Dampak dan 1 skala Kemungkinan (level 5).';
        }

        $impact = ! empty($ctx['impactCodes']) ? implode(', ', $ctx['impactCodes']) : '-';
        $like   = ! empty($ctx['likelihoodCodes']) ? implode(', ', $ctx['likelihoodCodes']) : '-';

        return "Skala dari taksonomi Risk: Dampak = {$impact} | Kemungkinan = {$like}.";
    }


    private static function ensureScaleMapModel($map): ?Trscalemap
    {
        if ($map instanceof Trscalemap) {
            return $map;
        }

        $id = null;

        if (is_object($map) && isset($map->i_id_scalemap)) {
            $id = (int) $map->i_id_scalemap;
        } elseif (is_array($map) && isset($map['i_id_scalemap'])) {
            $id = (int) $map['i_id_scalemap'];
        }

        if ($id > 0) {
            return Trscalemap::query()
                ->with(['scaleDetailA.scale', 'scaleDetailB.scale'])
                ->find($id);
        }

        return null;
    }

    private static function scaleMapOptionsForRiskInherent(?int $riskInherentId, ?int $includeMapId = null): array
    {
        $out = [];

        $ctx = self::scaleContextForRiskInherent($riskInherentId);

        $impactScaleIds     = $ctx['impactScaleIds'] ?? [];
        $likelihoodScaleIds = $ctx['likelihoodScaleIds'] ?? [];

        $maps = collect();

        if ($ctx['taxonomyId'] !== null && ! empty($impactScaleIds) && ! empty($likelihoodScaleIds)) {
            $maps = Trscalemap::query()
                ->with(['scaleDetailA.scale', 'scaleDetailB.scale'])
                ->whereHas('scaleDetailA', fn ($q) => $q->whereIn('i_id_scale', $impactScaleIds))
                ->whereHas('scaleDetailB', fn ($q) => $q->whereIn('i_id_scale', $likelihoodScaleIds))
                ->orderBy('i_map')
                ->get();
        }

        foreach ($maps as $m) {
            $model = self::ensureScaleMapModel($m);
            if (! $model) {
                continue;
            }
            $out[(int) $model->i_id_scalemap] = self::scaleMapOptionLabel($model);
        }

        if ($includeMapId && ! array_key_exists((int) $includeMapId, $out)) {
            $m = Trscalemap::query()
                ->with(['scaleDetailA.scale', 'scaleDetailB.scale'])
                ->find((int) $includeMapId);

            if ($m) {
                $out[(int) $m->i_id_scalemap] = (string) new HtmlString(
                    self::scaleMapOptionLabel($m) . ' <span style="opacity:.65;">(tidak sesuai skala taksonomi)</span>'
                );
            }
        }

        return $out;
    }


    private static function scaleMapOptionLabel(Trscalemap $map): string
    {
        $map->loadMissing(['scaleDetailA.scale', 'scaleDetailB.scale']);

        $rgb = (string) ($map->c_map ?? '156,163,175');

        $a = (int) ($map->scaleDetailA?->i_detail_score ?? 0);
        $b = (int) ($map->scaleDetailB?->i_detail_score ?? 0);
        $c = (int) ($map->i_map ?? ($a * $b));

        $aCode = trim((string) ($map->scaleDetailA?->scale?->v_scale ?? ''));
        $bCode = trim((string) ($map->scaleDetailB?->scale?->v_scale ?? ''));

        $aPrefix = $aCode !== '' ? ($aCode . ':') : '';
        $bPrefix = $bCode !== '' ? ($bCode . ':') : '';

        $cat   = ScaleMapForm::categoryFromMapValue($c);
        $level = (string) ($cat['label'] ?? '-');

        $labelText = sprintf(
            '%s%s (Dampak) x %s%s (Kemungkinan) = %s (%s)',
            $aPrefix,
            $a ?: '-',
            $bPrefix,
            $b ?: '-',
            $c ?: '-',
            $level
        );

        return (string) new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:8px;">
                <span style="
                    width:14px;height:14px;border-radius:4px;
                    border:1px solid rgba(107,114,128,0.8);
                    background:rgb(' . e($rgb) . ');
                "></span>
                <span>' . e($labelText) . '</span>
            </span>'
        );
    }

    private static function riskInherentOptionLabel(Tmriskinherent $ri): string
    {
        $ri->loadMissing(['scaleMapInherent', 'scaleMapResidual']);

        $riskInherentNumber = (string) ($ri->i_risk_inherent ?? $ri->i_id_riskinherent);

        $inRgb  = (string) ($ri->scaleMapInherent?->c_map ?? '156,163,175');
        $resRgb = (string) ($ri->scaleMapResidual?->c_map ?? '156,163,175');

        return (string) new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:10px;">
                <span style="font-weight:600;">' . e($riskInherentNumber) . '</span>
                <span style="opacity:.55;">|</span>

                <span style="display:inline-flex;align-items:center;gap:8px;">
                    <span style="
                        width:14px;height:14px;border-radius:4px;
                        border:1px solid rgba(107,114,128,0.8);
                        background:rgb(' . e($inRgb) . ');
                    "></span>
                    <span>(Inherent)</span>
                </span>

                <span style="opacity:.55;">|</span>

                <span style="display:inline-flex;align-items:center;gap:8px;">
                    <span style="
                        width:14px;height:14px;border-radius:4px;
                        border:1px solid rgba(107,114,128,0.8);
                        background:rgb(' . e($resRgb) . ');
                    "></span>
                    <span>(Residual)</span>
                </span>
            </span>'
        );
    }

    public static function configure(Schema $schema): Schema
    {
        $defaultYear = (string) now()->format('Y');

        return $schema
            ->columns(['default' => 1, 'lg' => 1])
            ->schema([
                Section::make('Risk Realization')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('c_realization_period')
                            ->required()
                            ->default($defaultYear . ' - 1')
                            ->afterStateHydrated(function ($state, Set $set): void {
                                $parsed = self::parseRealizationPeriod((string) $state);

                                $set('_realization_period_type', $parsed['type']);
                                $set('_realization_year', $parsed['year']);
                                $set('_realization_term', $parsed['term']);
                            }),

                        Grid::make(3)
                            ->columnSpan(1)
                            ->schema([
                                Select::make('_realization_period_type')
                                    ->label('Jenis')
                                    ->options(self::PERIOD_TYPES)
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->default('triwulan')
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) use ($defaultYear): void {
                                        $type = (string) $state;

                                        if (! $get('_realization_year')) {
                                            $set('_realization_year', $defaultYear);
                                        }

                                        $set('_realization_term', self::defaultTermForType($type));
                                        self::syncRealizationPeriod($set, $get);
                                    }),

                                Select::make('_realization_year')
                                    ->label('Tahun')
                                    ->options(self::yearRange())
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->default($defaultYear)
                                    ->disabled(fn (Get $get) => ! $get('_realization_period_type'))
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $type = (string) ($get('_realization_period_type') ?? '');
                                        if (! $get('_realization_term')) {
                                            $set('_realization_term', self::defaultTermForType($type));
                                        }
                                        self::syncRealizationPeriod($set, $get);
                                    }),

                                Select::make('_realization_term')
                                    ->label('Periode')
                                    ->options(fn (Get $get) => self::termOptionsForType((string) ($get('_realization_period_type') ?? '')))
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get) => ! $get('_realization_period_type') || ! $get('_realization_year'))
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncRealizationPeriod($set, $get)),
                            ]),

                       
                        Select::make('i_id_riskinherent')
                            ->label('Risk Inherent')
                            ->relationship(
                                name: 'riskInherent',
                                titleAttribute: 'i_risk_inherent',
                                modifyQueryUsing: function (Builder $query, ?Tmriskrealization $record): Builder {
                                    $access = self::accessContext();

                                    if (! $access['can_all']) {
                                        $orgPrefix = trim((string) $access['org_prefix']);
                                        if ($orgPrefix === '') {
                                            return $query->whereRaw('1=0');
                                        }

                                        $query->whereHas('risk', fn (Builder $q) => $q->where('c_org_owner', $orgPrefix));
                                    }

                                    $query->whereHas('risk', fn (Builder $q) => $q->where('c_risk_status', 9));

                                    $used = Tmriskrealization::query()->select('i_id_riskinherent');
                                    $current = (int) ($record?->i_id_riskinherent ?? 0);

                                    if ($current > 0) {
                                        $query->where(function (Builder $w) use ($used, $current) {
                                            $w->whereNotIn('i_id_riskinherent', $used)
                                              ->orWhere('i_id_riskinherent', $current);
                                        });
                                    } else {
                                        $query->whereNotIn('i_id_riskinherent', $used);
                                    }

                                    return $query;
                                }
                            )
                            ->preload()
                            ->searchable()
                            ->required()
                            ->allowHtml()
                            ->getOptionLabelFromRecordUsing(fn (Tmriskinherent $r) => self::riskInherentOptionLabel($r))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('i_id_scalemap', null);
                            }),

                        TextInput::make('p_risk_realization')
                            ->label('Persentase Realisasi (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),

                        TextInput::make('v_realization_cost')
                            ->label('Biaya Realisasi ($)')
                            ->numeric()
                            ->required(),

                        TextInput::make('v_exposure')
                            ->label('Exposure Realisasi')
                            ->numeric()
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('e_risk_realization')
                            ->label('Risk Realization')
                            ->rows(4)
                            ->columnSpanFull()
                            ->required(),

                        Select::make('i_id_scalemap')
                            ->label('Scale Map')
                            ->options(fn (Get $get) => self::scaleMapOptionsForRiskInherent(
                                riskInherentId: is_numeric($get('i_id_riskinherent')) ? (int) $get('i_id_riskinherent') : null,
                                includeMapId: is_numeric($get('i_id_scalemap')) ? (int) $get('i_id_scalemap') : null,
                            ))
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => ! $get('i_id_riskinherent'))
                            ->helperText(fn (Get $get) => self::helperTextForRiskInherent(
                                is_numeric($get('i_id_riskinherent')) ? (int) $get('i_id_riskinherent') : null,
                            ))
                            ->columnSpanFull(),

                        Section::make('Tampilan Grid')
                            ->columnSpanFull()
                            ->columns(3)
                            ->schema([
                                Toggle::make('_show_inherent')->label('Tampilkan Risiko Inheren')->default(true)->dehydrated(false)->live(),
                                Toggle::make('_show_residual')->label('Tampilkan Risiko Residual')->default(true)->dehydrated(false)->live(),
                                Toggle::make('_show_realization')->label('Tampilkan Realisasi Risiko')->default(true)->dehydrated(false)->live(),
                            ]),

                        ViewField::make('heatmap_preview')
                            ->label('')
                            ->view('filament.forms.components.risk-realization-heatmap')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->viewData(function (Get $get) {
                                $riskInhId = is_numeric($get('i_id_riskinherent')) ? (int) $get('i_id_riskinherent') : null;

                                $ctx = self::scaleContextForRiskInherent($riskInhId);
                                $impactScaleIds = $ctx['impactScaleIds'] ?? [];
                                $likelihoodScaleIds = $ctx['likelihoodScaleIds'] ?? [];

                                $maxImpact = ! empty($impactScaleIds)
                                    ? Trscaledetail::query()->whereIn('i_id_scale', $impactScaleIds)->max('i_detail_score')
                                    : Trscaledetail::query()->whereHas('scale', fn ($q) => $q->where('c_scale_type', '1'))->max('i_detail_score');

                                $maxLikelihood = ! empty($likelihoodScaleIds)
                                    ? Trscaledetail::query()->whereIn('i_id_scale', $likelihoodScaleIds)->max('i_detail_score')
                                    : Trscaledetail::query()->whereHas('scale', fn ($q) => $q->where('c_scale_type', '2'))->max('i_detail_score');

                                $colsCount = max(5, (int) ($maxImpact ?: 0));
                                $rowsCount = max(5, (int) ($maxLikelihood ?: 0));

                                $impactNames = [
                                    1 => 'Sangat Rendah', 2 => 'Rendah', 3 => 'Moderate', 4 => 'Tinggi', 5 => 'Sangat Tinggi',
                                ];
                                $likelihoodNames = [
                                    1 => 'Sangat Jarang', 2 => 'Jarang', 3 => 'Bisa', 4 => 'Sangat Mungkin', 5 => 'Hampir Pasti',
                                ];

                                $cols = [];
                                for ($c = 1; $c <= $colsCount; $c++) {
                                    $cols[$c] = ['index' => $c, 'label' => '(' . $c . ')', 'name' => $impactNames[$c] ?? ('Level ' . $c)];
                                }

                                $rows = [];
                                for ($r = 1; $r <= $rowsCount; $r++) {
                                    $letter = chr(ord('A') + ($r - 1));
                                    $rows[$r] = ['index' => $r, 'label' => $letter, 'name' => $likelihoodNames[$r] ?? ('Level ' . $r)];
                                }

                                $cells = [];
                                for ($r = 1; $r <= $rowsCount; $r++) {
                                    for ($c = 1; $c <= $colsCount; $c++) {
                                        $val = $r * $c;
                                        $cat = ScaleMapForm::categoryFromMapValue($val);
                                        $cells[$r][$c] = [
                                            'value' => $val,
                                            'label' => $cat['label'] ?? '—',
                                            'color' => $cat['defaultColor'] ?? null,
                                        ];
                                    }
                                }

                                $show = [
                                    'inherent' => $get('_show_inherent'),
                                    'residual' => $get('_show_residual'),
                                    'realization' => $get('_show_realization'),
                                ];
                                foreach ($show as $k => $v) {
                                    $show[$k] = ($v === null) ? true : (bool) $v;
                                }

                                $markers = ['inherent' => null, 'residual' => null, 'realization' => null];

                                if ($riskInhId) {
                                    $ri = Tmriskinherent::query()->find((int) $riskInhId);

                                    $inMapId  = is_numeric($ri?->i_id_scalemap) ? (int) $ri->i_id_scalemap : null;
                                    $resMapId = is_numeric($ri?->i_id_scalemapres) ? (int) $ri->i_id_scalemapres : null;

                                    if ($inMapId) {
                                        $in = Trscalemap::query()->with(['scaleDetailA', 'scaleDetailB'])->find($inMapId);
                                        if ($in && $in->scaleDetailA && $in->scaleDetailB) {
                                            $val = is_numeric($in->i_map) ? (int) $in->i_map : null;
                                            $cat = ScaleMapForm::categoryFromMapValue($val);
                                            $fill = (string) ($in->c_map ?: ($cat['defaultColor'] ?? ''));

                                            $markers['inherent'] = [
                                                'row' => (int) $in->scaleDetailB->i_detail_score,
                                                'col' => (int) $in->scaleDetailA->i_detail_score,
                                                'value' => $val,
                                                'label' => (string) ($cat['label'] ?? '—'),
                                                'fill' => $fill,
                                                'border' => self::BORDER_INHERENT,
                                                'legend' => 'Risiko Inheren',
                                            ];
                                        }
                                    }

                                    if ($resMapId) {
                                        $res = Trscalemap::query()->with(['scaleDetailA', 'scaleDetailB'])->find($resMapId);
                                        if ($res && $res->scaleDetailA && $res->scaleDetailB) {
                                            $val = is_numeric($res->i_map) ? (int) $res->i_map : null;
                                            $cat = ScaleMapForm::categoryFromMapValue($val);
                                            $fill = (string) ($res->c_map ?: ($cat['defaultColor'] ?? ''));

                                            $markers['residual'] = [
                                                'row' => (int) $res->scaleDetailB->i_detail_score,
                                                'col' => (int) $res->scaleDetailA->i_detail_score,
                                                'value' => $val,
                                                'label' => (string) ($cat['label'] ?? '—'),
                                                'fill' => $fill,
                                                'border' => self::BORDER_RESIDUAL,
                                                'legend' => 'Risiko Residual',
                                            ];
                                        }
                                    }
                                }

                                $mapId = is_numeric($get('i_id_scalemap')) ? (int) $get('i_id_scalemap') : null;
                                if ($mapId) {
                                    $map = Trscalemap::query()->with(['scaleDetailA', 'scaleDetailB'])->find($mapId);
                                    if ($map && $map->scaleDetailA && $map->scaleDetailB) {
                                        $val = is_numeric($map->i_map) ? (int) $map->i_map : null;
                                        $cat = ScaleMapForm::categoryFromMapValue($val);
                                        $fill = (string) ($map->c_map ?: ($cat['defaultColor'] ?? ''));

                                        $markers['realization'] = [
                                            'row' => (int) $map->scaleDetailB->i_detail_score,
                                            'col' => (int) $map->scaleDetailA->i_detail_score,
                                            'value' => $val,
                                            'label' => (string) ($cat['label'] ?? '—'),
                                            'fill' => $fill,
                                            'border' => self::BORDER_REALIZATION,
                                            'legend' => 'Realisasi Risiko',
                                        ];
                                    }
                                }

                                return [
                                    'data' => [
                                        'colsCount' => $colsCount,
                                        'rowsCount' => $rowsCount,
                                        'impactScaleCodes' => ! empty($ctx['impactCodes']) ? implode(', ', $ctx['impactCodes']) : null,
                                        'likelihoodScaleCodes' => ! empty($ctx['likelihoodCodes']) ? implode(', ', $ctx['likelihoodCodes']) : null,
                                        'cols' => $cols,
                                        'rows' => $rows,
                                        'cells' => $cells,
                                        'markers' => $markers,
                                        'show' => $show,
                                    ],
                                ];
                            }),
                    ]),
            ]);
    }
}
