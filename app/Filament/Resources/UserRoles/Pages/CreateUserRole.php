<?php

namespace App\Filament\Resources\UserRoles\Pages;

use App\Filament\Resources\UserRoles\UserRoleResource;
use App\Policies\SuperadminPolicy;
use App\Support\RoleCatalog;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUserRole extends CreateRecord
{
    protected static string $resource = UserRoleResource::class;

    protected function beforeCreate(): void
    {
        $data = (array) $this->form->getState();
        $roleId = (int) ($data['i_id_role'] ?? 0);

        if (RoleCatalog::isSuperadminRoleId($roleId) && ! SuperadminPolicy::isSuperadmin(auth()->user())) {
            Notification::make()
                ->danger()
                ->title('Tidak punya akses')
                ->body('Hanya Superadmin yang boleh menetapkan role Superadmin ke user lain.')
                ->send();

            $this->halt();
        }
    }
}