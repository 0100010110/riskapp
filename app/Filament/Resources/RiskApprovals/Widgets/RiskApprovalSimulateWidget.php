<?php

namespace App\Filament\Resources\RiskApprovals\Widgets;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Models\Tmrisk;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class RiskApprovalSimulateWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.widgets.risk-approval-simulate';

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public string $returnUrl = '';

    public static function canView(): bool
    {
        return RiskApprovalWorkflow::isRealSuperadmin();
    }

    public function mount(): void
    {
        if (! RiskApprovalWorkflow::isRealSuperadmin()) {
            return;
        }

        $base = RiskApprovalResource::getUrl('index'); 
        $query = request()->query(); 
        $this->returnUrl = $base . (! empty($query) ? ('?' . http_build_query($query)) : '');

        $sim = RiskApprovalWorkflow::getSimulateState();

        $this->form->fill([
            'as_role' => (string) ($sim['role_type'] ?? RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN),
            'as_div'  => (string) ($sim['org_prefix'] ?? ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(12)->schema([
                    Select::make('as_role')
                        ->label('Role')
                        ->options(RiskApprovalWorkflow::simulateRoleOptions())
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $role = strtolower(trim((string) $state));

                            if (in_array($role, [
                                RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
                                RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
                            ], true)) {
                                $set('as_div', 'GR');
                            }

                            if ($role === RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN) {
                                $set('as_div', '');
                            }
                        })
                        ->columnSpan(6),

                    Select::make('as_div')
                        ->label('Divisi')
                        ->options(fn (): array => $this->divisionOptions())
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->disabled(function (Get $get): bool {
                            $role = strtolower(trim((string) $get('as_role')));

                            if ($role === RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN) return true;

                            if (in_array($role, [
                                RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
                                RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
                            ], true)) return true;

                            return false;
                        })
                        ->required(function (Get $get): bool {
                            $role = strtolower(trim((string) $get('as_role')));

                            return in_array($role, [
                                RiskApprovalWorkflow::ROLE_TYPE_KADIV,
                                RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
                            ], true);
                        })
                        ->helperText(function (Get $get): string {
                            $role = strtolower(trim((string) $get('as_role')));

                            if ($role === RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN) {
                                return 'Superadmin: tampilkan semua risk register';
                            }

                            if (in_array($role, [
                                RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
                                RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
                            ], true)) {
                                return 'Admin/Approval GRC otomatis GR.';
                            }

                            if (in_array($role, [
                                RiskApprovalWorkflow::ROLE_TYPE_KADIV,
                                RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
                            ], true)) {
                                return 'Wajib pilih divisi untuk meniru scope role ini.';
                            }

                            return 'Opsional.';
                        })
                        ->columnSpan(6),
                ]),
            ])
            ->statePath('data');
    }

    public function apply(): void
    {
        $state = (array) $this->form->getState();

        $role = (string) ($state['as_role'] ?? RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN);
        $div  = (string) ($state['as_div'] ?? '');

        RiskApprovalWorkflow::setSimulateState($role, $div);
        RiskApprovalWorkflow::flushContext();

       $this->redirect($this->returnUrl, navigate: true);
    }

    public function resetSimulation(): void
    {
        RiskApprovalWorkflow::clearSimulateState();
        RiskApprovalWorkflow::flushContext();

        $this->form->fill([
            'as_role' => RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN,
            'as_div'  => '',
        ]);

        $this->redirect($this->returnUrl, navigate: true);
    }

    private function divisionOptions(): array
    {
        $rows = Tmrisk::query()
            ->select('c_org_owner')
            ->whereNotNull('c_org_owner')
            ->where('c_org_owner', '!=', '')
            ->distinct()
            ->orderBy('c_org_owner')
            ->pluck('c_org_owner')
            ->map(fn ($v) => strtoupper(trim((string) $v)))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        if (! in_array('GR', $rows, true)) {
            $rows[] = 'GR';
            sort($rows);
        }

        return array_combine($rows, $rows) ?: [];
    }
}
