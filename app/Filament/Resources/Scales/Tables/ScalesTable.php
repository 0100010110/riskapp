<?php

namespace App\Filament\Resources\Scales\Tables;

use App\Filament\Resources\Scales\ScaleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

class ScalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('v_scale')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('c_scale_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ((string) $state === '1') ? 'Dampak' : 'Kemungkinan')
                    ->colors([
                        'primary' => fn ($state) => (string) $state === '1',
                        'success' => fn ($state) => (string) $state === '2',
                    ])
                    ->sortable(),

                Tables\Columns\IconColumn::make('f_scale_finance')
                    ->label('Keuangan')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('i_scale')
                    ->label('Nilai')
                    ->formatStateUsing(function ($state, $record): string {
                        $val = is_numeric($state) ? (string) $state : (string) ($state ?? '');
                        $type = (string) ($record->c_scale_type ?? '');

                        if ($type === '1') {
                            return '$ ' . $val;
                        }

                        if ($type === '2') {
                            return $val . ' %';
                        }

                        return $val;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('n_scale_assumption')
                    ->label('Asumsi')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),
            ])

            ->filters([
                SelectFilter::make('c_scale_type')
                    ->label('Tipe')
                    ->options([
                        '1' => 'Dampak',
                        '2' => 'Kemungkinan',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }
                        return $query->where('c_scale_type', (string) $value);
                    }),

                SelectFilter::make('f_scale_finance')
                    ->label('Keuangan')
                    ->options([
                        '1' => 'Keuangan',
                        '0' => 'Non Keuangan',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }

                        $bool = ((string) $value === '1');
                        return $query->where('f_scale_finance', $bool);
                    }),
            ])

            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => ScaleResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => ScaleResource::canDelete($record))
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
                    ->visible(fn ($record) => ScaleResource::canEdit($record) || ScaleResource::canDelete($record)),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => ScaleResource::canDeleteAny())
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
                    ->visible(fn () => ScaleResource::canDeleteAny()),
            ])
            ->defaultSort('i_id_scale', 'desc');
    }
}
