<?php

namespace App\Filament\Resources\Risks\Pages;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Filament\Resources\Risks\RiskResource;
use App\Filament\Resources\Risks\Schemas\RiskForm;
use App\Support\RiskApprovalWorkflow;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Illuminate\Contracts\Support\Htmlable;

class EditRisk extends EditRecord
{
    use HasWizard;

    protected static string $resource = RiskResource::class;

    public function getSteps(): array
    {
        /** @var \App\Models\Tmrisk $record */
        $record = $this->getRecord();

        return RiskForm::wizardStepsForEdit($record);
    }

    protected function authorizeAccess(): void
    {
        /** @var \App\Models\Tmrisk $record */
        $record = $this->getRecord();

        // mode view (read-only)
        if (request()->boolean('view')) {
            abort_unless(static::getResource()::canView($record), 403);
            return;
        }

        $from = strtolower(trim((string) request()->query('from', '')));

        // ✅ SPECIAL RULE: edit dari approval cukup UPDATE (4), tidak butuh READ (1)
        if ($from === 'approval' && RiskApprovalWorkflow::canEditRiskOnApproval($record)) {
            abort_unless(static::getResource()::canEdit($record), 403);
            return;
        }

        // normal behaviour (akan butuh canEdit + akses normal resource)
        parent::authorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        if (request()->boolean('view')) {
            return 'View Risk Register';
        }

        return parent::getTitle();
    }

    protected function getFormActions(): array
    {
        if (request()->boolean('view')) {
            return [
                $this->getCancelFormAction(),
            ];
        }

        return parent::getFormActions();
    }

    public function getWizardComponent(): Component
    {
        $submitAction = request()->boolean('view')
            ? null
            : $this->getSubmitFormAction();

        return Wizard::make($this->getSteps())
            ->startOnStep($this->getStartStep())
            ->cancelAction($this->getCancelFormAction())
            ->submitAction($submitAction)
            ->alpineSubmitHandler("\$wire.{$this->getSubmitFormLivewireMethodName()}()")
            ->skippable(false)
            ->contained(false);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled(fn (): bool => request()->boolean('view'));
    }

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\Tmrisk $record */
        $record = $this->getRecord();

        if (! request()->boolean('view')) {
            return [];
        }

        $status = (int) ($record->c_risk_status ?? 0);

        $canApprove = RiskApprovalWorkflow::canApproveStatusForCurrentUser($status);
        $canReject  = $canApprove && RiskApprovalWorkflow::canRejectStatusForCurrentUser($status);

        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $canApprove)
                ->url(fn () => RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=approve'),

            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn () => $canReject)
                ->url(fn () => RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=reject'),

            Action::make('edit')
                ->label('Edit')
                ->color('primary')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => RiskApprovalWorkflow::canEditRiskOnApproval($record))
                ->url(fn () => RiskResource::getUrl('edit', ['record' => $record]) . '?from=approval'),

            Action::make('back')
                ->label('Back')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(function () {
                    $from = strtolower(trim((string) request()->query('from', '')));
                    return $from === 'approval'
                        ? RiskApprovalResource::getUrl('index')
                        : RiskResource::getUrl('index');
                }),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        $action = parent::getCancelFormAction();

        if (request()->boolean('view')) {
            $action->label('Back');

            $from = strtolower(trim((string) request()->query('from', '')));
            $action->url(
                $from === 'approval'
                    ? RiskApprovalResource::getUrl('index')
                    : RiskResource::getUrl('index')
            );
        }

        return $action;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Filament::auth()->user() ?? auth()->user();
        $uid = (int) ($user?->getAuthIdentifier() ?? 0);

        $data['i_update'] = $uid > 0 ? $uid : null;
        $data['d_update'] = now();

        return $data;
    }
}
