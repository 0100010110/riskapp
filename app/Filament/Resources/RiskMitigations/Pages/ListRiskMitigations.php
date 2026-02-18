<?php

namespace App\Filament\Resources\RiskMitigations\Pages;

use App\Filament\Resources\RiskMitigations\RiskMitigationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRiskMitigations extends ListRecords
{
    protected static string $resource = RiskMitigationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}
