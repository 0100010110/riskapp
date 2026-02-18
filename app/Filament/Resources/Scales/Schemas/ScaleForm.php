<?php

namespace App\Filament\Resources\Scales\Schemas;

use App\Models\Trscaledetail;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ScaleForm
{
    private static function unitByScaleType(?string $scaleType): string
    {
        $scaleType = (string) $scaleType;

        if ($scaleType === '1') return '$'; // Dampak
        if ($scaleType === '2') return '%'; // Kemungkinan

        return '';
    }

    private static function labelWithUnit(string $base, ?string $scaleType): string
    {
        $unit = self::unitByScaleType($scaleType);
        return $unit !== '' ? "{$base} ({$unit})" : $base;
    }

    private static function prefixByScaleType(?string $scaleType): string
    {
        return ((string) $scaleType === '2') ? 'SK' : 'DK';
    }

    private static function parsePrefixFromStored(?string $stored, ?string $scaleType): array
    {
        $stored = trim((string) $stored);

        if (preg_match('/^(SK|DP|DK)\s*/i', $stored, $m)) {
            $prefix = strtoupper($m[1]);
            $suffix = preg_replace('/^(SK|DP|DK)\s*/i', '', $stored) ?? '';
            return [$prefix, trim($suffix)];
        }

        $prefix = self::prefixByScaleType($scaleType);
        return [$prefix, $stored];
    }

    private static function syncStoredCode(Set $set, Get $get): void
    {
        $prefix = strtoupper(trim((string) $get('code_prefix')));
        $suffix = trim((string) $get('code_suffix'));

        if ($prefix === '') {
            $prefix = self::prefixByScaleType($get('c_scale_type'));
            $set('code_prefix', $prefix);
        }

        $set('v_scale', $prefix . $suffix);
    }

    
    private static function nextDetailScore(Get $get): int
    {
        $items = $get('../../details'); 
        if (! is_array($items)) {
            return 1;
        }

        $max = 0;
        foreach ($items as $row) {
            if (! is_array($row)) continue;

            $v = $row['i_detail_score'] ?? null;
            if ($v === null || $v === '') continue;

            if (is_numeric($v)) {
                $iv = (int) $v;
                if ($iv > $max) $max = $iv;
            }
        }

        return $max + 1;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 1,
            ])
            ->schema([
                Section::make('Skala (Header)')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        ToggleButtons::make('c_scale_type')
                            ->label('Tipe Skala')
                            ->options([
                                '1' => 'Dampak (Impact)',
                                '2' => 'Kemungkinan (Likelihood)',
                            ])
                            ->inline()
                            ->required()
                            ->default('1')
                            ->live()
                            ->formatStateUsing(fn ($state) => $state === null ? '1' : (string) $state)
                            ->dehydrateStateUsing(fn ($state) => (string) $state)
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $set('code_prefix', self::prefixByScaleType((string) $state));
                                self::syncStoredCode($set, $get);
                            }),

                        ToggleButtons::make('f_scale_finance')
                            ->label('Kategori')
                            ->options([
                                '1' => 'Keuangan (01)',
                                '0' => 'Non Keuangan (00)',
                            ])
                            ->inline()
                            ->required()
                            ->default('0')
                            ->live()
                            ->formatStateUsing(fn ($state) => $state === null ? '0' : (string) ((int) $state))
                            ->dehydrateStateUsing(fn ($state) => (int) $state),

                        TextInput::make('i_scale')
                            ->label(fn (Get $get) => self::labelWithUnit('Nilai', $get('c_scale_type')))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(999999999)
                            ->live(),

                        Hidden::make('v_scale')
                            ->required()
                            ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                                [$prefix, $suffix] = self::parsePrefixFromStored(
                                    is_string($state) ? $state : null,
                                    (string) $get('c_scale_type')
                                );

                                $set('code_prefix', $prefix);
                                $set('code_suffix', $suffix);
                                $set('v_scale', $prefix . $suffix);
                            }),

                        Grid::make(12)
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('code_prefix')
                                    ->label('Prefix')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(fn (Get $get) => self::prefixByScaleType($get('c_scale_type')))
                                    ->columnSpan(3),

                                TextInput::make('code_suffix')
                                    ->label('Kode')
                                    ->required()
                                    ->maxLength(18) 
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        self::syncStoredCode($set, $get);
                                    })
                                    ->columnSpan(9),
                            ]),

                        Textarea::make('n_scale_assumption')
                            ->label('Asumsi')
                            ->rows(3)
                            ->columnSpan(2),
                    ]),

                Section::make('Skala (Detail)')
                    ->columnSpanFull()
                    ->description('Baris detail bisa ditambah/kurangi sesuai kebutuhan.')
                    ->schema([
                        Repeater::make('details')
                            ->label('Detail Skala')
                            ->relationship('details')
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Skala Detail')
                            ->columns(4)
                            ->schema([
                                TextInput::make('i_detail_score')
                                    ->label('Skor')
                                    ->numeric()
                                    ->required()
                                    ->default(fn (Get $get) => self::nextDetailScore($get)),

                                Select::make('v_detail')
                                    ->label('Operator')
                                    ->options(Trscaledetail::OPERATORS)
                                    ->required(),

                                TextInput::make('c_detail')
                                    ->label(fn (Get $get) => self::labelWithUnit('Nilai / Batas', $get('../../c_scale_type')))
                                    ->numeric()
                                    ->required()
                                    ->live(),

                                Toggle::make('f_active')
                                    ->label('Aktif')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
