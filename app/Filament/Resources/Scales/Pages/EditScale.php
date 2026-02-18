<?php

namespace App\Filament\Resources\Scales\Pages;

use App\Filament\Resources\Scales\ScaleResource;
use Filament\Resources\Pages\EditRecord;

class EditScale extends EditRecord
{
    protected static string $resource = ScaleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['v_scale'] = ScaleResource::makeScaleCode(
            $data['c_scale_type'] ?? null,
            $data['f_scale_finance'] ?? null,
            $data['i_scale'] ?? null,
        );

        return $data;
    }
}
