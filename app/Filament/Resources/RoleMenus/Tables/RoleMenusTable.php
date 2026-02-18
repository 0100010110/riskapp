<?php

namespace App\Filament\Resources\RoleMenus\Tables;

use App\Filament\Resources\RoleMenus\RoleMenuResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\QueryException;

class RoleMenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('role.n_role')
                    ->label('Role')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn ($record) => (string) ($record->role?->n_role ?? '-')),
            ])
            ->defaultGroup('role.n_role')
            ->columns([
                Tables\Columns\TextColumn::make('i_id_rolemenu')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('role.n_role')
                    ->label('Role')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('menu.n_menu')
                    ->label('Menu')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('c_action')
                    ->label('Action')
                    ->sortable(),

                Tables\Columns\IconColumn::make('f_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => RoleMenuResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => RoleMenuResource::canDelete($record))
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
                        RoleMenuResource::canEdit($record) || RoleMenuResource::canDelete($record)
                    ),
            ])
            ->defaultSort('i_id_rolemenu', 'desc');
    }
}
