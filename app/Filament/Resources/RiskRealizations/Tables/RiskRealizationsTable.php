<?php

namespace App\Filament\Resources\RiskRealizations\Tables;

use App\Filament\Resources\RiskRealizations\RiskRealizationResource;
use App\Filament\Resources\ScaleMaps\Schemas\ScaleMapForm;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;

class RiskRealizationsTable
{
    protected static function colorName(?string $rgb): string
    {
        return match (trim((string) $rgb)) {
            '63,81,181'   => 'Blue',
            '76,175,80'   => 'Green',
            '255,235,59'  => 'Yellow',
            '255,152,0'   => 'Orange',
            '244,67,54'   => 'Red',
            '0,188,212'   => 'Cyan',
            '156,39,176'  => 'Purple',
            '233,30,99'   => 'Magenta',
            '121,85,72'   => 'Brown',
            '96,125,139'  => 'Blue Grey',
            default       => '-',
        };
    }

    protected static function colorBadge(?string $rgb): HtmlString
    {
        $rgb = trim((string) $rgb);
        if ($rgb === '') {
            return new HtmlString('-');
        }

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:8px;white-space:nowrap;">
                <span style="
                    width:14px;
                    height:14px;
                    border-radius:4px;
                    border:1px solid rgba(107,114,128,0.8);
                    background:rgb(' . e($rgb) . ');
                "></span>
                <span>' . e(self::colorName($rgb)) . '</span>
            </span>'
        );
    }

    protected static function formatPeriod(?string $period): string
    {
        $period = trim((string) $period);
        if ($period === '') {
            return '-';
        }

        if (preg_match('/^(\d{4})\s*-\s*([A-Za-z0-9]+)$/', $period, $m)) {
            $year = $m[1];
            $term = strtoupper($m[2]);

            if (str_starts_with($term, 'S')) {
                return $year . ' - ' . $term; // S1 / S2
            }

            if (str_starts_with($term, 'M')) {
                return $year . ' - ' . $term; // M01..M12
            }

            if (ctype_digit($term)) {
                return $year . ' - T' . $term; // 1..4 => T1..T4
            }

            return $year . ' - ' . $term;
        }

        return $period;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('riskInherent.i_risk_inherent')
                    ->label('Risk Inherent')
                    ->sortable()
                    ->searchable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('c_realization_period')
                    ->label('Periode')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => self::formatPeriod(is_string($state) ? $state : null)),

                Tables\Columns\TextColumn::make('p_risk_realization')
                    ->label('%')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => ($state === null || $state === '') ? '-' : (string) $state),

                Tables\Columns\TextColumn::make('v_realization_cost')
                    ->label('$')
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('scaleMap.i_map')
                    ->label('Nilai Map')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('scaleMap.c_map')
                    ->label('Color')
                    ->html()
                    ->alignCenter()
                    ->getStateUsing(function ($record) {
                        $rgb = $record->scaleMap?->c_map;

                        if (! $rgb) {
                            $mapVal = $record->scaleMap?->i_map;
                            $mapVal = is_numeric($mapVal) ? (int) $mapVal : null;

                            $rgb = ScaleMapForm::categoryFromMapValue($mapVal)['defaultColor'] ?? null;
                        }

                        return $rgb;
                    })
                    ->formatStateUsing(fn ($state) => self::colorBadge($state ? (string) $state : null)),

                Tables\Columns\TextColumn::make('v_exposure')
                    ->label('Exposure')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => ($state === null || $state === '') ? '-' : (string) $state),

                Tables\Columns\TextColumn::make('i_entry')
                    ->label('Created By')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Created At')
                    ->html()
                    ->formatStateUsing(function ($state): string {
                        if (blank($state)) {
                            return '-';
                        }

                        $dt = $state instanceof \Carbon\CarbonInterface
                            ? $state
                            : Carbon::parse((string) $state);

                        $date = e($dt->format('d M Y'));
                        $time = e($dt->format('H:i:s'));

                        return "{$date}<br>{$time}";
                    })
                    ->sortable(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => RiskRealizationResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => RiskRealizationResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Record masih direferensikan tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn ($record) =>
                        RiskRealizationResource::canEdit($record)
                        || RiskRealizationResource::canDelete($record)
                    ),
            ])
            ->defaultSort('i_id_riskrealization', 'desc');
    }
}
