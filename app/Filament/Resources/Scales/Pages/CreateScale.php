<?php

namespace App\Filament\Resources\Scales\Pages;

use App\Filament\Resources\Scales\ScaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScale extends CreateRecord
{
    protected static string $resource = ScaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['v_scale'] = strtoupper(trim((string) ($data['v_scale'] ?? '')));

        return $data;
    }
}