<?php

namespace App\Filament\Resources\RiskRealizations\Pages;

use App\Filament\Resources\RiskRealizations\RiskRealizationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRiskRealization extends EditRecord
{
    protected static string $resource = RiskRealizationResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => static::getResource()::canDelete($this->record)),
        ];
    }
}
