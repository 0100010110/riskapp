<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CallCenter;
use App\Filament\Widgets\ChangePassword;
use App\Filament\Widgets\CustomAccount;
use App\Filament\Widgets\ExampleChart;
use App\Filament\Widgets\ExampleRelationshipChart;
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Support\Facades\Hash;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Home';
    protected static string $routePath = 'home';

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            // FilamentInfoWidget::class,
            // StatsOverview::class,
            // ExampleChart::class,
            // ExampleRelationshipChart::class
        ];
    }
}
