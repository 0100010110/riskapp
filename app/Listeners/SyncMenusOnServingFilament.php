<?php

namespace App\Listeners;

use App\Services\FilamentMenuSyncService;
use Filament\Events\ServingFilament;

class SyncMenusOnServingFilament
{
    public function handle(ServingFilament $event): void
    {
        app(FilamentMenuSyncService::class)->sync();
    }
}
