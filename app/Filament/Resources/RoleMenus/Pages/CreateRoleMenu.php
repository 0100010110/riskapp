<?php

namespace App\Filament\Resources\RoleMenus\Pages;

use App\Filament\Resources\RoleMenus\RoleMenuResource;
use App\Models\Trrolemenu;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateRoleMenu extends CreateRecord
{
    protected static string $resource = RoleMenuResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getFormColumns(): int|array
    {
        return 1;
    }

    /**
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $roleId = (int) ($data['i_id_role'] ?? 0);
        $items  = $data['items'] ?? [];

        if ($roleId <= 0 || ! is_array($items) || count($items) < 1) {
            Notification::make()
                ->danger()
                ->title('Data tidak valid')
                ->body('Pilih Role dan tambahkan minimal 1 menu.')
                ->send();

            throw new Halt();
        }

        $created = [];

        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $menuId = (int) ($item['i_id_menu'] ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $created[] = Trrolemenu::create([
                'i_id_role' => $roleId,
                'i_id_menu' => $menuId,
                'c_action'  => (int) ($item['c_action'] ?? 0),
                'f_active'  => (bool) ($item['f_active'] ?? true),
            ]);
        }

        if (empty($created)) {
            Notification::make()
                ->danger()
                ->title('Tidak ada data dibuat')
                ->body('Pastikan setiap item memiliki Menu.')
                ->send();

            throw new Halt();
        }

        return $created[0];
    }
}
