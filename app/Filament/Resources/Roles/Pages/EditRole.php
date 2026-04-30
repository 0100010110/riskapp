<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\RoleCatalog;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    if (RoleCatalog::isSuperadminRole($record)) {
                        return false;
                    }

                    return static::getResource()::canDelete($record);
                })
                ->before(function (): void {
                    $record = $this->getRecord();

                    if (RoleCatalog::isSuperadminRole($record)) {
                        Notification::make()
                            ->danger()
                            ->title('Tidak bisa menghapus role Superadmin')
                            ->body('Role Superadmin (A-0) bersifat permanen dan tidak dapat dihapus.')
                            ->send();

                        $this->halt();
                    }
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if (RoleCatalog::isSuperadminRole($record)) {
            Notification::make()
                ->danger()
                ->title('Tidak bisa mengubah role Superadmin')
                ->body('Role Superadmin (A-0) bersifat permanen dan tidak dapat diubah.')
                ->send();

            $this->halt();
        }
    }
}