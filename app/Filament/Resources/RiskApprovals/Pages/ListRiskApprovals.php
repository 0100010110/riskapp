<?php

namespace App\Filament\Resources\RiskApprovals\Pages;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Filament\Resources\RiskApprovals\Widgets\RiskApprovalSimulateWidget;
use Filament\Resources\Pages\ListRecords;

class ListRiskApprovals extends ListRecords
{
    protected static string $resource = RiskApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RiskApprovalSimulateWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1; 
    }
}
