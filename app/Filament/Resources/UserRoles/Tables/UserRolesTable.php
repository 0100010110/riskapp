<?php

namespace App\Filament\Resources\UserRoles\Tables;

use App\Filament\Resources\UserRoles\UserRoleResource;
use App\Services\EmployeeCacheService;
use Filament\Actions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class UserRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('i_id_userrole')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('i_id_user')
                    ->label('User')
                    ->formatStateUsing(function ($state): string {
                        $label = app(EmployeeCacheService::class)->labelForId($state ? (int) $state : null);
                        $label = trim((string) $label);

                        if ($label === '') {
                            return '-';
                        }

                        $parts = array_values(array_filter(array_map('trim', explode('|', $label)), fn ($v) => $v !== ''));

                        if (count($parts) >= 3) {
                            return $parts[0] . ' | ' . $parts[2];
                        }

                        if (count($parts) === 2) {
                            return $parts[0] . ' | ' . $parts[1];
                        }

                        if (preg_match('/^\s*(\d+)\s*\|\s*\d+\s*\|\s*([^|]+)\s*(\||$)/u', $label, $m)) {
                            return trim($m[1]) . ' | ' . trim($m[2]);
                        }

                        return $label;
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('role.n_role')
                    ->label('Role')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => UserRoleResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => UserRoleResource::canDelete($record)),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn ($record) => UserRoleResource::canEdit($record) || UserRoleResource::canDelete($record)),
            ])
            ->defaultSort('i_id_userrole', 'desc');
    }
}
