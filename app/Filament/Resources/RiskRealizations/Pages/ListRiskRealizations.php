<?php

namespace App\Filament\Resources\RiskRealizations\Pages;

use App\Filament\Resources\RiskRealizations\RiskRealizationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRiskRealizations extends ListRecords
{
    protected static string $resource = RiskRealizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}
