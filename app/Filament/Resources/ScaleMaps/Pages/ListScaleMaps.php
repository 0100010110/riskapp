<?php

namespace App\Filament\Resources\ScaleMaps\Pages;

use App\Filament\Resources\ScaleMaps\ScaleMapResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScaleMaps extends ListRecords
{
    protected static string $resource = ScaleMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => ScaleMapResource::canCreate()),
        ];
    }
}
