<?php

namespace App\Filament\Resources\RoleMenus\Pages;

use App\Filament\Resources\RoleMenus\RoleMenuResource;
use App\Models\Trrolemenu;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditRoleMenu extends EditRecord
{
    protected static string $resource = RoleMenuResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getFormColumns(): int|array
    {
        return 1;
    }

    
    protected static function maskToArray(int $mask): array
    {
        $bits = [1, 2, 4, 8, 16];
        $out = [];

        foreach ($bits as $b) {
            if (($mask & $b) === $b) {
                $out[] = (string) $b;
            }
        }

        return $out;
    }

    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Trrolemenu $record */
        $record = $this->getRecord();
        $roleId = (int) $record->i_id_role;

        $rows = Trrolemenu::query()
            ->where('i_id_role', $roleId)
            ->orderBy('i_id_rolemenu')
            ->get();

        return [
            'i_id_role' => $roleId,
            'items' => $rows->map(fn (Trrolemenu $row) => [
                'i_id_rolemenu' => (int) $row->i_id_rolemenu,
                'i_id_menu'     => (int) $row->i_id_menu,

                'c_action'      => self::maskToArray((int) $row->c_action),

                'f_active'      => (bool) $row->f_active,
            ])->all(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Trrolemenu $record */
        $roleId = (int) $record->i_id_role;
        $items  = $data['items'] ?? [];

        if (! is_array($items) || count($items) < 1) {
            Notification::make()
                ->danger()
                ->title('Data tidak valid')
                ->body('Tambahkan minimal 1 menu.')
                ->send();

            return $record;
        }

        $existing = Trrolemenu::query()
            ->where('i_id_role', $roleId)
            ->get()
            ->keyBy('i_id_rolemenu');

        $keepIds = [];

        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $menuId = (int) ($item['i_id_menu'] ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $rowId = (int) ($item['i_id_rolemenu'] ?? 0);

            $payload = [
                'i_id_role' => $roleId,
                'i_id_menu' => $menuId,
                'c_action'  => (int) ($item['c_action'] ?? 0), 
                'f_active'  => (bool) ($item['f_active'] ?? true),
            ];

            if ($rowId > 0 && $existing->has($rowId)) {
                /** @var Trrolemenu $row */
                $row = $existing->get($rowId);
                $row->update($payload);
                $keepIds[] = $rowId;
                continue;
            }

            $newRow = Trrolemenu::create($payload);
            $keepIds[] = (int) $newRow->i_id_rolemenu;
        }

        $canDelete = RoleMenuResource::canDelete($record);
        if ($canDelete) {
            foreach ($existing as $id => $row) {
                $id = (int) $id;
                if ($id === (int) $record->i_id_rolemenu) {
                    continue;
                }
                if (! in_array($id, $keepIds, true)) {
                    $row->delete();
                }
            }
        }

        $record->refresh();
        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
