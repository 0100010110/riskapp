<?php

namespace App\Filament\Resources\RiskInherents\Pages;

use App\Filament\Resources\RiskInherents\RiskInherentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRiskInherent extends EditRecord
{
    protected static string $resource = RiskInherentResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => static::getResource()::canDelete($this->record)),
        ];
    }
}
