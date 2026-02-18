<?php

namespace App\Filament\Resources\ScaleMaps\Tables;

use App\Filament\Resources\ScaleMaps\ScaleMapResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;

class ScaleMapsTable
{
    protected static function colorName(string $rgb): string
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
            default       => 'Custom',
        };
    }

    protected static function colorBadge(string $rgb): string
    {
        return (string) new HtmlString(
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
            ->columns([
                Tables\Columns\TextColumn::make('scaleDetailA.scale.v_scale')
                    ->label('Kode Dampak')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scaleDetailA.i_detail_score')
                    ->label('Dampak')
                    ->sortable(),

                Tables\Columns\TextColumn::make('scaleDetailB.scale.v_scale')
                    ->label('Kode Kemungkinan')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scaleDetailB.i_detail_score')
                    ->label('Kemungkinan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('i_map')
                    ->label('Nilai Map')
                    ->sortable(),

                Tables\Columns\TextColumn::make('c_map')
                    ->label('Color')
                    ->html()
                    ->formatStateUsing(fn ($state) =>
                        self::colorBadge((string) $state)
                    ),

                Tables\Columns\TextColumn::make('n_map')
                    ->label('Penjelasan')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => ScaleMapResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => ScaleMapResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Data masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn ($record) =>
                        ScaleMapResource::canEdit($record) || ScaleMapResource::canDelete($record)
                    ),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => ScaleMapResource::canDeleteAny())
                        ->action(function ($records) {
                            try {
                                $records->each->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Sebagian data masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn () => ScaleMapResource::canDeleteAny()),
            ])
            ->defaultSort('i_id_scalemap', 'desc');
    }
}
