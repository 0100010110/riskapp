<?php

namespace App\Filament\Resources\LossEvents\Pages;

use App\Filament\Resources\LossEvents\LossEventResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditLossEvent extends EditRecord
{
    protected static string $resource = LossEventResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Filament::auth()->user() ?? auth()->user();
        $uid  = (int) ($user?->getAuthIdentifier() ?? 0);

        $data['i_update'] = $uid > 0 ? $uid : null;
        $data['d_update'] = now();

        return $data;
    }
}
