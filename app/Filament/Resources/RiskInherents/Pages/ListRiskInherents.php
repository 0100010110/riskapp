<?php

namespace App\Filament\Resources\RiskInherents\Pages;

use App\Filament\Resources\RiskInherents\RiskInherentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRiskInherents extends ListRecords
{
    protected static string $resource = RiskInherentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}
