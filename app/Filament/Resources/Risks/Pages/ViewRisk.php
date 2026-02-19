<?php

namespace App\Filament\Resources\Risks\Pages;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Filament\Resources\Risks\RiskResource;
use App\Models\Tmrisk;
use App\Support\RiskApprovalWorkflow;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRisk extends ViewRecord
{
    protected static string $resource = RiskResource::class;

    protected function authorizeAccess(): void
    {
        /** @var Tmrisk $record */
        $record = $this->getRecord();

        abort_unless(RiskResource::canView($record), 403);
    }

    protected function getHeaderActions(): array
    {
        /** @var Tmrisk $record */
        $record = $this->getRecord();

        $from = strtolower(trim((string) request()->query('from', '')));
        $status = (int) ($record->c_risk_status ?? 0);

        $canApprove = RiskApprovalWorkflow::canApproveStatusForCurrentUser($status);
        $canReject  = $canApprove && RiskApprovalWorkflow::canRejectStatusForCurrentUser($status);

        $approveUrl = RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=approve';
        $rejectUrl  = RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=reject';

        return [
            Actions\Action::make('back')
                ->label('Back')
                ->color('gray')
                ->url(fn () => $from === 'approval'
                    ? RiskApprovalResource::getUrl('index')
                    : RiskResource::getUrl('index')
                ),

            Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => RiskResource::canEdit($record))
                ->url(function () use ($record, $from) {
                    $url = RiskResource::getUrl('edit', ['record' => $record]);

                    if ($from === 'approval') {
                        $url .= '?from=approval';
                    }

                    return $url;
                })
                ->openUrlInNewTab(false),

            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn () => $canReject)
                ->url($rejectUrl)
                ->openUrlInNewTab(false),

            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $canApprove)
                ->url($approveUrl)
                ->openUrlInNewTab(false),
        ];
    }
}
