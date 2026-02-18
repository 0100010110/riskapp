<?php

namespace App\Filament\Resources\RiskInherents\Tables;

use App\Filament\Resources\RiskInherents\RiskInherentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;

class RiskInherentsTable
{
    protected static function colorName(?string $rgb): string
    {
        return match ($rgb) {
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
        if (! $rgb) {
            return new HtmlString('-');
        }

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:8px;">
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

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query->with([
                    'risk',
                    'scaleMapInherent',
                    'scaleMapResidual',
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('i_risk_inherent')
                    ->label('No')
                    ->sortable()
                    ->alignCenter()
                    ->searchable(),

                Tables\Columns\TextColumn::make('risk.c_risk_year')
                    ->label('Year')
                    ->sortable(),

                Tables\Columns\TextColumn::make('risk.i_risk')
                    ->label('Risk No')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('scaleMapInherent.c_map')
                    ->label('Inherent')
                    ->html()
                    ->formatStateUsing(fn ($state) => self::colorBadge($state)),

                Tables\Columns\TextColumn::make('v_exposure')
                    ->label(new HtmlString('Exposure<br>(Inherent)'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('scaleMapResidual.c_map')
                    ->label('Residual')
                    ->html()
                    ->formatStateUsing(fn ($state) => self::colorBadge($state)),

                Tables\Columns\TextColumn::make('v_exposure_res')
                    ->label(new HtmlString('Exposure<br>(Residual)'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('i_entry')
                    ->label('Created By'),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Created At')
                    ->dateTime(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => RiskInherentResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => RiskInherentResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException) {
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
                        RiskInherentResource::canEdit($record) ||
                        RiskInherentResource::canDelete($record)
                    ),
            ])
            ->defaultSort('i_id_riskinherent', 'desc');
    }
}
