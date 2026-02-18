<?php

namespace App\Filament\Resources\RiskMitigations\Pages;

use App\Filament\Resources\RiskMitigations\RiskMitigationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRiskMitigation extends EditRecord
{
    protected static string $resource = RiskMitigationResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => static::getResource()::canDelete($this->record)),
        ];
    }
}
