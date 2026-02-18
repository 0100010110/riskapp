<?php

namespace App\Filament\Resources\RiskInherents\Schemas;

use App\Filament\Resources\ScaleMaps\Schemas\ScaleMapForm;
use App\Models\Tmrisk;
use App\Models\Tmriskinherent;
use App\Models\Tmtaxonomyscale;
use App\Models\Trscalemap;
use App\Models\Trscale;
use App\Models\Trscaledetail;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RiskInherentForm
{
    /**
     * @return array{
     *   role_type:string,
     *   is_super:bool,
     *   user_id:int,
     *   org_prefix:string
     * }
     */
    protected static function accessContext(): array
    {
        $ctx = RiskApprovalWorkflow::context();

        return [
            'role_type'   => (string) ($ctx['role_type'] ?? ''),
            'is_super'    => (bool) ($ctx['is_superadmin'] ?? false),
            'user_id'     => (int) ($ctx['user_id'] ?? 0),
            'org_prefix'  => strtoupper(trim((string) ($ctx['org_prefix'] ?? ''))),
        ];
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
    protected static function scaleContextForRisk(?int $riskId): array
    {
        $riskId = (int) ($riskId ?: 0);

        if ($riskId <= 0) {
            return [
                'taxonomyId' => null,
                'impactScaleIds' => [],
                'likelihoodScaleIds' => [],
                'impactCodes' => [],
                'likelihoodCodes' => [],
            ];
        }

        $risk = Tmrisk::query()
            ->select(['i_id_risk', 'i_id_taxonomy'])
            ->find($riskId);

        $taxonomyId = (int) ($risk?->i_id_taxonomy ?? 0);

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

    protected static function helperTextForRisk(?int $riskId): string
    {
        $ctx = self::scaleContextForRisk($riskId);

        $impact = ! empty($ctx['impactCodes']) ? implode(', ', $ctx['impactCodes']) : '-';
        $like   = ! empty($ctx['likelihoodCodes']) ? implode(', ', $ctx['likelihoodCodes']) : '-';

        if ($ctx['taxonomyId'] === null) {
            return 'Pilih Risk terlebih dahulu.';
        }

        if (empty($ctx['impactScaleIds']) || empty($ctx['likelihoodScaleIds'])) {
            return 'Skala pada taksonomi Risk belum lengkap. Wajib ada minimal 1 skala Dampak dan 1 skala Kemungkinan pada taxonomy level 5.';
        }

        return "Skala dari taksonomi Risk: Dampak = {$impact} | Kemungkinan = {$like}.";
    }

    protected static function scaleMapOptions(?int $riskId = null, ?int $includeMapId = null): array
    {
        $out = [];

        $ctx = self::scaleContextForRisk($riskId);

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

        foreach ($maps as $map) {
            $rgb = $map->c_map;

            $aScore = $map->scaleDetailA?->i_detail_score;
            $bScore = $map->scaleDetailB?->i_detail_score;
            $mapValue = $map->i_map;

            $aCode = trim((string) ($map->scaleDetailA?->scale?->v_scale ?? ''));
            $bCode = trim((string) ($map->scaleDetailB?->scale?->v_scale ?? ''));

            $aPrefix = $aCode !== '' ? ($aCode . ':') : '';
            $bPrefix = $bCode !== '' ? ($bCode . ':') : '';

            $labelText = sprintf(
                '%s%s (Dampak) × %s%s (Kemungkinan) = %s',
                $aPrefix,
                $aScore ?? '-',
                $bPrefix,
                $bScore ?? '-',
                $mapValue ?? '-'
            );

            $out[$map->i_id_scalemap] = (string) new HtmlString(
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

        if ($includeMapId && ! array_key_exists($includeMapId, $out)) {
            $m = Trscalemap::query()
                ->with(['scaleDetailA.scale', 'scaleDetailB.scale'])
                ->find((int) $includeMapId);

            if ($m) {
                $rgb = $m->c_map;

                $aScore = $m->scaleDetailA?->i_detail_score;
                $bScore = $m->scaleDetailB?->i_detail_score;
                $mapValue = $m->i_map;

                $aCode = trim((string) ($m->scaleDetailA?->scale?->v_scale ?? ''));
                $bCode = trim((string) ($m->scaleDetailB?->scale?->v_scale ?? ''));

                $aPrefix = $aCode !== '' ? ($aCode . ':') : '';
                $bPrefix = $bCode !== '' ? ($bCode . ':') : '';

                $labelText = sprintf(
                    '%s%s (Dampak) × %s%s (Kemungkinan) = %s',
                    $aPrefix,
                    $aScore ?? '-',
                    $bPrefix,
                    $bScore ?? '-',
                    $mapValue ?? '-'
                );

                $out[(int) $m->i_id_scalemap] = (string) new HtmlString(
                    '<span style="display:inline-flex;align-items:center;gap:8px;">
                        <span style="
                            width:14px;height:14px;border-radius:4px;
                            border:1px solid rgba(107,114,128,0.8);
                            background:rgb(' . e($rgb) . ');
                        "></span>
                        <span>' . e($labelText) . ' <span style="opacity:.65;">(tidak sesuai skala taksonomi)</span></span>
                    </span>'
                );
            }
        }

        return $out;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Risk Inherent')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('i_id_risk')
                        ->label('Risk')
                        ->relationship(
                            name: 'risk',
                            titleAttribute: 'i_risk',
                            modifyQueryUsing: function (Builder $query, ?Tmriskinherent $record): Builder {
                                $access = self::accessContext();

                                $roleType = $access['role_type'];
                                $isSuper  = $access['is_super'];

                               if (! $isSuper && ! in_array($roleType, [
                                    RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
                                    RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
                                    RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
                                ], true)) {
                                    return $query->whereRaw('1=0');
                                }

                               if (method_exists(RiskApprovalWorkflow::class, 'applyRiskRegisterScope')) {
                                    $query = RiskApprovalWorkflow::applyRiskRegisterScope($query);
                                }

                               $currentRiskId = (int) ($record?->i_id_risk ?? 0);

                                if ($currentRiskId > 0) {
                                    $query->where(function (Builder $q) use ($currentRiskId) {
                                        $q->where('c_risk_status', 4)
                                          ->orWhere('i_id_risk', $currentRiskId);
                                    });
                                } else {
                                    $query->where('c_risk_status', 4);
                                }

                               $usedRiskIds = Tmriskinherent::query()->select('i_id_risk');

                                if ($currentRiskId > 0) {
                                    $query->where(function (Builder $q) use ($usedRiskIds, $currentRiskId) {
                                        $q->whereNotIn('i_id_risk', $usedRiskIds)
                                            ->orWhere('i_id_risk', $currentRiskId);
                                    });
                                } else {
                                    $query->whereNotIn('i_id_risk', $usedRiskIds);
                                }

                                return $query;
                            }
                        )
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(function (Tmrisk $record): string {
                            $event = trim((string) ($record->e_risk_event ?? ''));
                            $event = mb_strlen($event) > 60 ? mb_substr($event, 0, 60) . '…' : $event;

                            $riskNo = trim((string) ($record->i_risk ?? ''));
                            $riskNo = $riskNo !== '' ? $riskNo : '-';

                            $div = trim((string) ($record->c_org_owner ?? ''));
                            $div = $div !== '' ? $div : '-';

                            return "{$record->c_risk_year} - {$riskNo} | {$div} | {$event}";
                        })
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('i_id_scalemap', null);
                            $set('i_id_scalemapres', null);
                        })
                        ->required(),

                    TextInput::make('i_risk_inherent')
                        ->label('Risk Inherent Number')
                        ->required()
                        ->maxLength(255),

                    Select::make('i_id_scalemap')
                        ->label('Scale Map (Inherent)')
                        ->options(fn (Get $get) => self::scaleMapOptions(
                            riskId: is_numeric($get('i_id_risk')) ? (int) $get('i_id_risk') : null,
                            includeMapId: is_numeric($get('i_id_scalemap')) ? (int) $get('i_id_scalemap') : null,
                        ))
                        ->allowHtml()
                        ->preload()
                        ->searchable()
                        ->live()
                        ->disabled(fn (Get $get) => ! $get('i_id_risk'))
                        ->helperText(fn (Get $get) => self::helperTextForRisk(
                            is_numeric($get('i_id_risk')) ? (int) $get('i_id_risk') : null,
                        ))
                        ->required(),

                    TextInput::make('v_exposure')
                        ->label('Risk Inherent Exposure')
                        ->maxLength(50),

                    Select::make('i_id_scalemapres')
                        ->label('Scale Map (Residual)')
                        ->options(fn (Get $get) => self::scaleMapOptions(
                            riskId: is_numeric($get('i_id_risk')) ? (int) $get('i_id_risk') : null,
                            includeMapId: is_numeric($get('i_id_scalemapres')) ? (int) $get('i_id_scalemapres') : null,
                        ))
                        ->allowHtml()
                        ->preload()
                        ->searchable()
                        ->live()
                        ->disabled(fn (Get $get) => ! $get('i_id_risk'))
                        ->helperText(fn (Get $get) => self::helperTextForRisk(
                            is_numeric($get('i_id_risk')) ? (int) $get('i_id_risk') : null,
                        ))
                        ->required(),

                    TextInput::make('v_exposure_res')
                        ->label('Risk Residual Exposure')
                        ->maxLength(50),

                    ViewField::make('heatmap_preview')
                        ->label('')
                        ->view('filament.forms.components.risk-realization-heatmap')
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->viewData(function (Get $get) {
                            $riskId = is_numeric($get('i_id_risk')) ? (int) $get('i_id_risk') : null;

                            $ctx = self::scaleContextForRisk($riskId);
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

                            $markers = [
                                'inherent' => null,
                                'residual' => null,
                                'realization' => null,
                            ];

                            $inMapId = is_numeric($get('i_id_scalemap')) ? (int) $get('i_id_scalemap') : null;
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
                                        'border' => '0,230,118',
                                        'legend' => 'Risiko Inheren',
                                    ];
                                }
                            }

                            $resMapId = is_numeric($get('i_id_scalemapres')) ? (int) $get('i_id_scalemapres') : null;
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
                                        'border' => '41,121,255',
                                        'legend' => 'Risiko Residual',
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
                                    'show' => [
                                        'inherent' => true,
                                        'residual' => true,
                                        'realization' => false,
                                    ],
                                ],
                            ];
                        }),
                ]),
        ]);
    }
}
