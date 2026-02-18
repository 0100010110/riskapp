<?php

namespace App\Filament\Resources\LossEvents\Pages;

use App\Filament\Resources\LossEvents\LossEventResource;
use App\Models\Tmlostevent;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateLossEvent extends CreateRecord
{
    protected static string $resource = LossEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user() ?? auth()->user();
        $uid  = (int) ($user?->getAuthIdentifier() ?? 0);

        $data['i_entry'] = $uid > 0 ? $uid : 0;
        $data['d_entry'] = now();

        $data['c_lostevent_status'] = isset($data['c_lostevent_status'])
            ? (int) $data['c_lostevent_status']
            : Tmlostevent::STATUS_DRAFT;

        $data['i_update'] = null;
        $data['d_update'] = null;

        return $data;
    }
}
