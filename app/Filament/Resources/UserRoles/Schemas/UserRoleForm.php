<?php

namespace App\Filament\Resources\UserRoles\Schemas;

use App\Models\Trrole;
use App\Policies\SuperadminPolicy;
use App\Services\EmployeeCacheService;
use App\Support\RoleCatalog;
use Filament\Forms;
use Filament\Schemas\Schema;

class UserRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('i_id_user')
                ->label('User (dari Cache API)')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    return app(EmployeeCacheService::class)->searchOptions($search, 50);
                })
                ->getOptionLabelUsing(function ($value): string {
                    return app(EmployeeCacheService::class)->labelForId($value ? (int) $value : null);
                })
                ->disabled(fn (string $operation): bool => $operation === 'edit'),

            Forms\Components\Select::make('i_id_role')
                ->label('Role')
                ->required()
                ->searchable()
                ->options(function (): array {
                    $q = Trrole::query()->orderBy('n_role');

                   if (! SuperadminPolicy::isSuperadmin(auth()->user())) {
                        $sid = RoleCatalog::superadminRoleId();

                        if ($sid !== null) {
                            $q->where('i_id_role', '!=', $sid);
                        } else {
                            $q->where('c_role', '!=', RoleCatalog::SUPERADMIN_ROLE_CODE)
                              ->whereRaw("LOWER(COALESCE(n_role,'')) <> ?", ['superadmin'])
                              ->whereRaw("LOWER(COALESCE(c_role,'')) <> ?", ['superadmin']);
                        }
                    }

                    return $q->pluck('n_role', 'i_id_role')->toArray();
                }),
        ]);
    }
}