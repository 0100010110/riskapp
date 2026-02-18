<?php

namespace App\Filament\Resources\LossEvents\Pages;

use App\Filament\Resources\LossEvents\LossEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLossEvents extends ListRecords
{
    protected static string $resource = LossEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => LossEventResource::canCreate()),
        ];
    }
}
