<?php

namespace App\Filament\Resources\UserRoles\Pages;

use App\Filament\Resources\UserRoles\UserRoleResource;
use App\Policies\SuperadminPolicy;
use App\Support\RoleCatalog;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUserRole extends EditRecord
{
    protected static string $resource = UserRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    return static::getResource()::canDelete($record);
                })
                ->before(function (): void {
                    $record = $this->getRecord();
                    $roleId = (int) ($record?->i_id_role ?? 0);

                    if (RoleCatalog::isSuperadminRoleId($roleId) && ! SuperadminPolicy::isSuperadmin(auth()->user())) {
                        Notification::make()
                            ->danger()
                            ->title('Tidak punya akses')
                            ->body('Hanya Superadmin yang boleh menghapus assignment role Superadmin.')
                            ->send();

                        $this->halt();
                    }
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $currentRoleId = (int) ($record?->i_id_role ?? 0);

        if (RoleCatalog::isSuperadminRoleId($currentRoleId) && ! SuperadminPolicy::isSuperadmin(auth()->user())) {
            Notification::make()
                ->danger()
                ->title('Tidak punya akses')
                ->body('Hanya Superadmin yang boleh mengubah assignment role Superadmin.')
                ->send();

            $this->halt();
        }

        $data = (array) $this->form->getState();
        $newRoleId = (int) ($data['i_id_role'] ?? 0);

        if (RoleCatalog::isSuperadminRoleId($newRoleId) && ! SuperadminPolicy::isSuperadmin(auth()->user())) {
            Notification::make()
                ->danger()
                ->title('Tidak punya akses')
                ->body('Hanya Superadmin yang boleh menetapkan role Superadmin ke user.')
                ->send();

            $this->halt();
        }
    }
}