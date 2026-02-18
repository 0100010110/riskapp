<?php

namespace App\Filament\Resources\ScaleMaps\Pages;

use App\Filament\Resources\ScaleMaps\ScaleMapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScaleMap extends CreateRecord
{
    protected static string $resource = ScaleMapResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['i_map'] = ScaleMapResource::computeMapValue(
            isset($data['i_id_scale_a']) ? (int) $data['i_id_scale_a'] : null,
            isset($data['i_id_scale_b']) ? (int) $data['i_id_scale_b'] : null,
        );

        return $data;
    }
}
