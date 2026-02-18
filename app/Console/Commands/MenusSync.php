<?php

namespace App\Console\Commands;

use App\Services\FilamentMenuSyncService;
use Illuminate\Console\Command;

class MenusSync extends Command
{
    protected $signature = 'menus:sync {--force : Paksa sync walau baru saja sync}';
    protected $description = 'Sync menu Filament (Resources/Pages) ke tabel trmenu';

    public function handle(FilamentMenuSyncService $sync): int
    {
        $count = $sync->sync((bool) $this->option('force'));

        $this->info("OK. Inserted {$count} new menu(s) into trmenu.");

        return self::SUCCESS;
    }
}
