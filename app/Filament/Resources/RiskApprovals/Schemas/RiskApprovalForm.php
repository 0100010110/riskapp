<?php

namespace App\Filament\Resources\RiskApprovals\Schemas;

use App\Models\Tmrisk;
use App\Models\Tmriskapprove;
use App\Models\Trrole;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RiskApprovalForm
{
    private const SESSION_LOCK_RISK_KEY = 'risk_approval.locked_risk_id';

    private static function taxonomyPrefixForLevel(?int $level): string
    {
        return match ((int) $level) {
            1 => 'TR',
            2 => 'KR',
            3 => 'PR',
            4 => 'SR',
            5 => 'DR',
            default => 'TM',
        };
    }

    private static function formatTaxonomyCode(?string $code, ?int $level): string
    {
        $code = trim((string) $code);
        if ($code === '') return '';

        if (preg_match('/^(TM|TR|KR|PR|SR|DR)/i', $code)) {
            return strtoupper($code);
        }

        return self::taxonomyPrefixForLevel($level) . $code;
    }

    public static function configure(Schema $schema): Schema
    {
        $ctx = RiskApprovalWorkflow::context();

        $riskIdFromRequest = 0;
        try {
            $riskIdFromRequest = (int) request()->query('risk', 0);
        } catch (\Throwable) {
            $riskIdFromRequest = 0;
        }

        try {
            if (request()->isMethod('GET') && $riskIdFromRequest <= 0) {
                session()->forget(self::SESSION_LOCK_RISK_KEY);
            }
        } catch (\Throwable) {
        }

        try {
            if ($riskIdFromRequest > 0) {
                session()->put(self::SESSION_LOCK_RISK_KEY, $riskIdFromRequest);
            } else {
                $riskIdFromRequest = (int) session()->get(self::SESSION_LOCK_RISK_KEY, 0);
            }
        } catch (\Throwable) {
        }

        $pickableStatuses = RiskApprovalWorkflow::actionableStatusesForCurrentUser();
        $roleId = RiskApprovalWorkflow::currentApproverRoleId();

        return $schema
            ->columns(['default' => 1, 'lg' => 1])
            ->schema([
                Section::make('Risk Approval')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('i_id_risk')
                            ->label('Risk')
                            ->relationship(
                                name: 'risk',
                                titleAttribute: 'i_risk',
                                modifyQueryUsing: function (Builder $query) use ($pickableStatuses, $riskIdFromRequest) {
                                    $query->where(function (Builder $q) use ($pickableStatuses, $riskIdFromRequest) {
                                        if (! empty($pickableStatuses)) {
                                            $q->whereIn('c_risk_status', $pickableStatuses);
                                        } else {
                                            $q->whereRaw('1=0');
                                        }

                                        if ($riskIdFromRequest > 0) {
                                            $q->orWhere('i_id_risk', $riskIdFromRequest);
                                        }
                                    });

                                    RiskApprovalWorkflow::applyApprovalListScope($query);

                                    $query->with(['taxonomy']);

                                    return $query
                                        ->orderByDesc('c_risk_year')
                                        ->orderBy('i_risk');
                                },
                            )
                            ->getOptionLabelFromRecordUsing(function (Tmrisk $record): string {
                                $event = trim((string) $record->e_risk_event);
                                $event = mb_strlen($event) > 60 ? (mb_substr($event, 0, 60) . '…') : $event;

                                $taxCodeRaw = (string) ($record->taxonomy?->c_taxonomy ?? '');
                                $taxLevel   = (int) ($record->taxonomy?->c_taxonomy_level ?? 0);
                                $taxCode    = self::formatTaxonomyCode($taxCodeRaw, $taxLevel);

                                $riskNo = trim((string) $record->i_risk);
                                $year   = trim((string) $record->c_risk_year);

                                return "{$year} - {$riskNo} | {$taxCode} | {$event}";
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (?Tmriskapprove $record) => $record?->i_id_risk ?: ($riskIdFromRequest > 0 ? $riskIdFromRequest : null))
                            ->afterStateHydrated(function (Select $component) use ($riskIdFromRequest) {
                                if ($riskIdFromRequest > 0) {
                                    $component->state($riskIdFromRequest);
                                }
                            })
                            ->disabled(fn () => $riskIdFromRequest > 0)
                            ->dehydrated(true),

                        Select::make('i_id_role')
                            ->label('Role (Approver)')
                            ->relationship(
                                name: 'role',
                                titleAttribute: 'n_role',
                                modifyQueryUsing: fn (Builder $query) => $query->where('f_active', true),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Trrole $record) => "{$record->c_role} - {$record->n_role}")
                            ->getOptionLabelUsing(function ($value): ?string {
                                $id = (int) $value;
                                if ($id <= 0) return null;

                                $r = Trrole::query()
                                    ->select(['c_role', 'n_role'])
                                    ->where('i_id_role', $id)
                                    ->first();

                                return $r ? "{$r->c_role} - {$r->n_role}" : null;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (?Tmriskapprove $record) => $record?->i_id_role ?: $roleId)
                            ->afterStateHydrated(function (Select $component) use ($roleId) {
                                if (! $component->getState() && $roleId) {
                                    $component->state($roleId);
                                }
                            })
                            ->disabled()
                            ->dehydrated(true),

                        TextInput::make('i_emp')
                            ->label('Approved By (NIK)')
                            ->required()
                            ->maxLength(50)
                            ->default(fn (?Tmriskapprove $record) => $record?->i_emp ?: RiskApprovalWorkflow::currentUserNik())
                            ->dehydrated(true),

                        TextInput::make('n_emp')
                            ->label('Approved By (Name)')
                            ->required()
                            ->maxLength(255)
                            ->default(fn (?Tmriskapprove $record) => $record?->n_emp ?: RiskApprovalWorkflow::currentUserName())
                            ->dehydrated(true),
                    ]),
            ]);
    }
}
