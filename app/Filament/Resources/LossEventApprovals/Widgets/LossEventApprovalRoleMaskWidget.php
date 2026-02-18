<?php

namespace App\Filament\Resources\LossEventApprovals\Widgets;

use App\Filament\Resources\LossEventApprovals\LossEventApprovalResource;
use App\Models\Tmlostevent;
use App\Services\EmployeeCacheService;
use App\Support\LossEventApprovalWorkflow;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class LossEventApprovalRoleMaskWidget extends Widget implements HasForms
{
    use InteractsWithForms;
    protected string $view = 'filament.widgets.loss-event-approval-role-mask-widget';

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

        $base = LossEventApprovalResource::getUrl('index');
        $query = request()->query();
        $this->returnUrl = $base . (! empty($query) ? ('?' . http_build_query($query)) : '');

        $sim = LossEventApprovalWorkflow::getSimulateState();

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
                        ->label('Masking Role (Simulasi)')
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

                            // Superadmin: div kosong
                            if ($role === RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN) {
                                $set('as_div', '');
                            }
                        })
                        ->columnSpan(6),

                    Select::make('as_div')
                        ->label('Divisi (2 huruf) — khusus Officer/Kadiv')
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
                                return 'Superadmin: tampilkan semua Loss Event.';
                            }

                            if (in_array($role, [
                                RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
                                RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
                            ], true)) {
                                return 'Admin/Approval GRC otomatis GR (tidak dibatasi divisi).';
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

        LossEventApprovalWorkflow::setSimulateState($role, $div);
        LossEventApprovalWorkflow::flushContext();

        $this->redirect($this->returnUrl, navigate: true);
    }

    public function resetSimulation(): void
    {
        LossEventApprovalWorkflow::clearSimulateState();
        LossEventApprovalWorkflow::flushContext();

        $this->form->fill([
            'as_role' => RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN,
            'as_div'  => '',
        ]);

        $this->redirect($this->returnUrl, navigate: true);
    }

    
    private function divisionOptions(): array
    {
        $ids = Tmlostevent::query()
            ->select('i_entry')
            ->whereNotNull('i_entry')
            ->distinct()
            ->limit(2000)
            ->pluck('i_entry')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        $prefixes = [];

        try {
            /** @var EmployeeCacheService $svc */
            $svc = app(EmployeeCacheService::class);

            foreach ($ids as $uid) {
                try {
                    $row = $svc->findById($uid);
                } catch (\Throwable) {
                    $row = null;
                }

                $org = is_array($row)
                    ? trim((string) ($row['organisasi'] ?? $row['organization'] ?? $row['org'] ?? ''))
                    : '';

                $p = $this->normalizeOrgPrefix($org);
                if ($p !== '') {
                    $prefixes[] = $p;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $prefixes = array_values(array_unique(array_filter($prefixes)));

        if (! in_array('GR', $prefixes, true)) {
            $prefixes[] = 'GR';
        }

        sort($prefixes);

        return array_combine($prefixes, $prefixes) ?: [];
    }

    private function normalizeOrgPrefix(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';

        if (preg_match('/^([A-Za-z]{2})/', $value, $m)) {
            return strtoupper($m[1]);
        }

        return strtoupper(substr($value, 0, 2));
    }
}
