<?php

namespace App\Filament\Resources\RiskApprovals\Pages;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditRiskApproval extends EditRecord
{
    protected static string $resource = RiskApprovalResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Edit Risk Approval';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Update Approval')
            ->icon('heroicon-o-pencil-square');
    }
}
