<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('i_id_role')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('c_role')->label('Role Code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('n_role')->label('Role Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('e_role')->label('Description')->limit(50),

                Tables\Columns\IconColumn::make('f_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('d_entry')->label('Created At')->dateTime()->sortable(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => RoleResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => RoleResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Role masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->visible(fn ($record) => RoleResource::canEdit($record) || RoleResource::canDelete($record)),
            ])
            ->defaultSort('i_id_role', 'desc');
    }
}
