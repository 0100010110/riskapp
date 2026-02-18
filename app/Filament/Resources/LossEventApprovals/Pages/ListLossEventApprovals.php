<?php

namespace App\Filament\Resources\LossEventApprovals\Pages;

use App\Filament\Resources\LossEventApprovals\LossEventApprovalResource;
use App\Filament\Resources\LossEventApprovals\Widgets\LossEventApprovalRoleMaskWidget;
use Filament\Resources\Pages\ListRecords;

class ListLossEventApprovals extends ListRecords
{
    protected static string $resource = LossEventApprovalResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            LossEventApprovalRoleMaskWidget::class,
        ];
    }
}
