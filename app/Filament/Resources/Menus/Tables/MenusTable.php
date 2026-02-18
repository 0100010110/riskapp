<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Filament\Resources\Menus\MenuResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('i_id_menu')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('c_menu')->label('Menu Code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('n_menu')->label('Menu Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('e_menu')->label('Description')->limit(50),

                Tables\Columns\IconColumn::make('f_active')->label('Active')->boolean()->sortable(),

                Tables\Columns\TextColumn::make('d_entry')->label('Created At')->dateTime()->sortable(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => MenuResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => MenuResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Menu masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->visible(fn ($record) => MenuResource::canEdit($record) || MenuResource::canDelete($record)),
            ])
            ->defaultSort('i_id_menu', 'desc');
    }
}
