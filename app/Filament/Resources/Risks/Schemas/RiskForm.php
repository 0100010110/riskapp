<?php

namespace App\Filament\Resources\Risks\Schemas;

use App\Models\Tmrisk;
use App\Models\Tmtaxonomy;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RiskForm
{
    private const STATUS_TAHAP1_APPROVED = 4;

    private static function isViewMode(): bool
    {
        try {
            return request()->boolean('view');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function isViewPage(): bool
    {
        if (self::isViewMode()) {
            return true;
        }

        try {
            $route = request()->route();
            if (! $route) {
                return false;
            }

            $name = '';
            try {
                $name = (string) $route->getName();
            } catch (\Throwable) {
                $name = '';
            }

            $uri = '';
            try {
                $uri = (string) $route->uri();
            } catch (\Throwable) {
                $uri = '';
            }

            return (
                ($name !== '' && str_ends_with($name, '.view'))
                || ($uri === 'risks/{record}')
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private static function hasMeaningfulValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        $v = trim((string) $value);
        if ($v === '' || strtolower($v) === 'null') {
            return false;
        }

        return true;
    }

    /** @param array<int,string> $attributes */
    private static function recordHasAnyValue(?Tmrisk $record, array $attributes): bool
    {
        if (! $record) {
            return false;
        }

        foreach ($attributes as $attr) {
            try {
                if (self::hasMeaningfulValue($record->getAttribute($attr))) {
                    return true;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return false;
    }

    private static function status(?Tmrisk $record): int
    {
        return (int) ($record?->c_risk_status ?? 0);
    }

    private static function lockTopSteps(?Tmrisk $record): bool
    {
        if (self::isViewMode()) {
            return true;
        }

        return $record !== null && self::status($record) >= self::STATUS_TAHAP1_APPROVED;
    }

    private static function canEditAfterTahap1(?Tmrisk $record): bool
    {
        if (self::isViewMode()) {
            return false;
        }

        return $record !== null && self::status($record) >= self::STATUS_TAHAP1_APPROVED;
    }

    /**
     * @return array<string,string>
     */
    private static function yearOptions(?Tmrisk $record = null): array
    {
        $now = (int) now()->format('Y');
        $years = range($now, $now + 10);

        $recordYear = (int) ($record?->c_risk_year ?? 0);
        if ($recordYear > 0 && ! in_array($recordYear, $years, true)) {
            $years[] = $recordYear;
        }

        sort($years);

        $out = [];
        foreach ($years as $y) {
            $out[(string) $y] = (string) $y;
        }

        return $out;
    }

    /**
     * @return array<int, Step>
     */
    public static function wizardStepsForCreate(): array
    {
        return [
            Step::make('Identitas Risiko')->schema([
                self::identitySection(),
            ]),
            Step::make('Deskripsi Risiko')->schema([
                self::descriptionSection(),
            ]),
            Step::make('Nilai Dampak')->schema([
                self::impactValueSection(),
            ]),
            Step::make('Primary Risk')->schema([
                self::primaryRiskSection(),
            ]),
        ];
    }

    /**
     * @return array<int, Step>
     */
    public static function wizardStepsForEdit(?Tmrisk $record = null): array
    {
        $steps = [
            Step::make('Identitas Risiko')->schema([
                self::identitySection(),
            ]),
            Step::make('Deskripsi Risiko')->schema([
                self::descriptionSection(),
            ]),
            Step::make('Nilai Dampak')->schema([
                self::impactValueSection(),
            ]),
        ];

        if ($record && self::status($record) >= self::STATUS_TAHAP1_APPROVED) {
            $steps[] = Step::make('KRI & Threshold')->schema([
                self::kriSection(),
            ]);

            $steps[] = Step::make('Periode & Kontrol')->schema([
                self::exposureAndControlSection(),
            ]);
        }

        $steps[] = Step::make('Primary Risk')->schema([
            self::primaryRiskSection(),
        ]);

        return $steps;
    }

    public static function identitySection(): Section
    {
        return Section::make('Identitas Risiko')
            ->columnSpanFull()
            ->columns(2)
            ->schema([
                Select::make('i_id_taxonomy')
                    ->label('Taxonomy (Dasar Risiko)')
                    ->relationship(
                        name: 'taxonomy',
                        titleAttribute: 'n_taxonomy',
                        modifyQueryUsing: fn (Builder $query) => $query->where('c_taxonomy_level', 5),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Tmtaxonomy $record) => "{$record->c_taxonomy} - {$record->n_taxonomy}")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record))
                    ->helperText('Dipilih dari level 5 (Dasar Risiko).'),

                Select::make('c_risk_year')
                    ->label('Tahun Risiko')
                    ->options(fn (?Tmrisk $record = null) => self::yearOptions($record))
                    ->default(fn () => (string) now()->format('Y'))
                    ->native(false)
                    ->searchable(false)
                    ->required()
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),

                TextInput::make('i_risk')
                    ->label('Nomor Risiko')
                    ->maxLength(50)
                    ->disabled()
                    ->dehydrated(true)
                    ->default('')
                    ->afterStateHydrated(function (Set $set, $state): void {
                        $v = trim((string) $state);

                        if ($v === '' || strtolower($v) === 'null') {
                            $set('i_risk', '');
                        }
                    })
                    ->formatStateUsing(function ($state): string {
                        $v = trim((string) $state);
                        return ($v === '' || strtolower($v) === 'null') ? '' : $v;
                    })
                    ->dehydrateStateUsing(function ($state): string {
                        $v = trim((string) $state);
                        return ($v === '' || strtolower($v) === 'null') ? '' : $v;
                    })
                    ->helperText('Nomor Risiko akan digenerate otomatis saat status final tahap tertentu (observer).'),

                Select::make('c_risk_status')
                    ->label('Status')
                    ->options(fn () => Tmrisk::statusOptions())
                    ->default(0)
                    ->disabled()
                    ->dehydrated(true)
                    ->helperText('Status diubah lewat workflow approval, bukan dari form Risk Register.'),

                TextInput::make('c_org_owner')
                    ->label('Owner Organization')
                    ->required()
                    ->maxLength(2)
                    ->minLength(2)
                    ->default(fn (?Tmrisk $record = null) => $record?->c_org_owner ?: RiskApprovalWorkflow::currentUserOrgPrefix())
                    ->disabled()
                    ->dehydrated(true)
                    ->helperText('Otomatis diisi dari organisasi user yang login (2 huruf awal).'),

                TextInput::make('c_org_impact')
                    ->label('Impacted Organization')
                    ->nullable()
                    ->maxLength(100)
                    ->placeholder('Contoh: DIV-OPS / HR / dll')
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),
            ]);
    }

    public static function descriptionSection(): Section
    {
        return Section::make('Deskripsi Risiko')
            ->columnSpanFull()
            ->columns(2)
            ->schema([
                Textarea::make('e_risk_event')
                    ->label('Risk Event')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull()
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),

                Textarea::make('e_risk_cause')
                    ->label('Risk Cause')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull()
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),

                Textarea::make('e_risk_impact')
                    ->label('Risk Impact (Uraian)')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull()
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),
            ]);
    }

    public static function impactValueSection(): Section
    {
        return Section::make('Nilai Dampak')
            ->columnSpanFull()
            ->columns(2)
            ->schema([
                Textarea::make('v_risk_impact')
                    ->label('Risk Impact Value')
                    ->required()
                    ->rows(2)
                    ->placeholder('Contoh: Rp 2.000.000.000 / Penurunan 5% / dll')
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),

                TextInput::make('c_risk_impactunit')
                    ->label('Impact Unit')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('Contoh: Rupiah / % / Hari / Unit')
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),
            ]);
    }

    public static function primaryRiskSection(): Section
    {
        return Section::make('Apakah Risk ini Merupakan Primary Risk ?')
            ->columnSpanFull()
            ->columns(1)
            ->schema([
                Select::make('f_risk_primary')
                    ->hiddenLabel()
                    ->native(false)
                    ->required()
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->default('0')
                    ->afterStateHydrated(function (Set $set, $state): void {
                        $set('f_risk_primary', ((bool) $state) ? '1' : '0');
                    })
                    ->dehydrateStateUsing(fn ($state) => (string) $state === '1')
                    ->disabled(fn (?Tmrisk $record) => self::lockTopSteps($record)),
            ]);
    }

    public static function kriSection(): Section
    {
        return Section::make('KRI (Key Risk Indicator) & Threshold')
            ->columnSpanFull()
            ->visible(function (?Tmrisk $record): bool {
                if (self::isViewPage()) {
                    return self::recordHasAnyValue($record, [
                        'e_kri',
                        'c_kri_unit',
                        'c_kri_operator',
                        'v_threshold_safe',
                        'v_threshold_caution',
                        'v_threshold_danger',
                    ]);
                }

                return true;
            })
            ->columns(2)
            ->schema([
                Textarea::make('e_kri')
                    ->label('KRI')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull()
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                TextInput::make('c_kri_unit')
                    ->label('KRI Unit')
                    ->maxLength(50)
                    ->nullable()
                    ->placeholder('Contoh: % / Rupiah / Hari')
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                Select::make('c_kri_operator')
                    ->label('KRI Operator')
                    ->options([
                        '>'  => '>',
                        '>=' => '>=',
                        '<'  => '<',
                        '<=' => '<=',
                        '='  => '=',
                    ])
                    ->nullable()
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                TextInput::make('v_threshold_safe')
                    ->label('Threshold Safe')
                    ->numeric()
                    ->nullable()
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                TextInput::make('v_threshold_caution')
                    ->label('Threshold Caution')
                    ->numeric()
                    ->nullable()
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                TextInput::make('v_threshold_danger')
                    ->label('Threshold Danger')
                    ->numeric()
                    ->nullable()
                    ->columnSpanFull()
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),
            ]);
    }

    public static function exposureAndControlSection(): Section
    {
        return Section::make('Periode Paparan, & Efektivitas Kontrol')
            ->columnSpanFull()
            ->visible(function (?Tmrisk $record): bool {
                if (self::isViewPage()) {
                    return self::recordHasAnyValue($record, [
                        'd_exposure_period',
                        'c_control_effectiveness',
                        'e_exist_ctrl',
                    ]);
                }

                return true;
            })
            ->columns(2)
            ->schema([
                TextInput::make('d_exposure_period')
                    ->label('Exposure Period')
                    ->nullable()
                    ->maxLength(50)
                    ->placeholder('Contoh: Bulanan / Tahunan / Q1-Q4')
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                TextInput::make('c_control_effectiveness')
                    ->label('Control Effectiveness')
                    ->nullable()
                    ->maxLength(50)
                    ->placeholder('Contoh: Low / Medium / High / Not Assessed')
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),

                Textarea::make('e_exist_ctrl')
                    ->label('Eksisting Kontrol')
                    ->nullable()
                    ->rows(3)
                    ->placeholder('Contoh: SOP / review berjenjang / monitoring rutin / kontrol sistem / dll')
                    ->columnSpanFull()
                    ->disabled(fn (?Tmrisk $record) => ! self::canEditAfterTahap1($record)),
            ]);
    }

    
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 1,
            ])
            ->schema([
                self::identitySection(),
                self::descriptionSection(),
                self::impactValueSection(),
                self::kriSection(),
                self::exposureAndControlSection(),
                self::primaryRiskSection(),
            ]);
    }
}