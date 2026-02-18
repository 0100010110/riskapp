<?php

namespace App\Filament\Resources\RiskApprovals\Tables;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Filament\Resources\Risks\RiskResource;
use App\Models\Tmrisk;
use App\Models\Tmriskapprove;
use App\Support\RiskApprovalWorkflow;
use App\Support\TaxonomyFormatter;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RiskApprovalsTable
{
    public static function configure(Table $table): Table
    {
        $ctx      = RiskApprovalWorkflow::context();
        $roleType = (string) ($ctx['role_type'] ?? '');
        $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);

        $empId = RiskApprovalWorkflow::currentEmpId();
        $actionable = RiskApprovalWorkflow::actionableStatusesForCurrentUser();

        $riskTable    = (new Tmrisk())->getTable();
        $approveTable = (new Tmriskapprove())->getTable();

        $useYearDivisionGroup = in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ], true);

        $queryCb = function () use (
            $isSuper,
            $empId,
            $actionable,
            $riskTable,
            $approveTable,
            $useYearDivisionGroup
        ) {
            $q = Tmrisk::query()
                ->select($riskTable . '.*')
                ->with(['taxonomy', 'latestApproval.role']);

            $q->selectRaw("COALESCE({$riskTable}.d_update, {$riskTable}.d_entry) as updated_at_sort");

            if ($useYearDivisionGroup) {
                $q->selectRaw(
                    "({$riskTable}.c_risk_year::text || '|' || COALESCE(TRIM(UPPER({$riskTable}.c_org_owner)),'')) as approval_group"
                );
            } else {
                $q->selectRaw(
                    "(COALESCE({$riskTable}.c_risk_year::text,'')) as approval_group"
                );
            }

            $q = RiskApprovalWorkflow::applyApprovalListScope($q);

            if ($isSuper) {
                return $q;
            }

            return $q->where(function (Builder $w) use ($actionable, $empId, $riskTable, $approveTable) {
                if (! empty($actionable)) {
                    $w->whereIn($riskTable . '.c_risk_status', $actionable);
                } else {
                    $w->whereRaw('1=0');
                }

                if ($empId > 0) {
                    $w->orWhereExists(function ($sq) use ($empId, $riskTable, $approveTable) {
                        $sq->select(DB::raw(1))
                            ->from($approveTable . ' as ra')
                            ->whereColumn('ra.i_id_risk', $riskTable . '.i_id_risk')
                            ->where('ra.i_emp', $empId);
                    });
                }
            });
        };

        $table = $table
            ->query($queryCb)
            ->recordUrl(null);

        if (method_exists($table, 'groupingSettingsHidden')) {
            $table->groupingSettingsHidden();
        }

        return $table
            ->defaultSort('updated_at_sort', 'desc')
            ->filters([
                Filter::make('need_action')
                    ->label('Need Action')
                    ->visible(fn () => $roleType === RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC)
                    ->query(function (Builder $query) use ($actionable, $empId, $riskTable, $approveTable) {
                        if ($empId <= 0) {
                            return $query->whereIn($riskTable . '.c_risk_status', $actionable);
                        }

                        return $query
                            ->whereIn($riskTable . '.c_risk_status', $actionable)
                            ->where(function (Builder $w) use ($empId, $riskTable, $approveTable) {
                                $w->whereNotExists(function ($sq) use ($empId, $riskTable, $approveTable) {
                                    $sq->select(DB::raw(1))
                                        ->from($approveTable . ' as ra')
                                        ->whereColumn('ra.i_id_risk', $riskTable . '.i_id_risk')
                                        ->where('ra.i_emp', $empId);
                                });

                                $w->orWhereExists(function ($sq) use ($empId, $riskTable, $approveTable) {
                                    $sq->select(DB::raw(1))
                                        ->from($approveTable . ' as ra')
                                        ->whereColumn('ra.i_id_risk', $riskTable . '.i_id_risk')
                                        ->where('ra.i_emp', $empId)
                                        ->whereRaw(
                                            "COALESCE(ra.d_entry, CURRENT_TIMESTAMP) < COALESCE({$riskTable}.d_update, {$riskTable}.d_entry)"
                                        );
                                });
                            });
                    }),
            ])
            ->groups([
                Group::make('approval_group')
                    ->label('Group')
                    ->collapsible()

                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query)

                    ->getKeyFromRecordUsing(fn (Tmrisk $record): string =>
                        trim((string) ($record->approval_group ?? '')) ?: '-'
                    )
                    ->getTitleFromRecordUsing(function (Tmrisk $record): string {
                        $key = trim((string) ($record->approval_group ?? '')) ?: '-';

                        if (str_contains($key, '|')) {
                            [$year, $div] = array_pad(explode('|', $key, 2), 2, '-');
                            $year = trim((string) $year) ?: '-';
                            $div  = trim((string) $div) ?: '-';
                            return "{$year} — Divisi: {$div}";
                        }

                        return $key;
                    }),
            ])
            ->defaultGroup('approval_group')
            ->columns([
                Tables\Columns\TextColumn::make('taxonomy.c_taxonomy')
                    ->label('Taxonomy Code')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state, Tmrisk $record) => TaxonomyFormatter::formatCode(
                        $state,
                        (int) ($record->taxonomy?->c_taxonomy_level ?? null)
                    )),

                Tables\Columns\TextColumn::make('i_risk')
                    ->label('Risk No')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('c_org_owner')
                    ->label('Divisi')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('e_risk_event')
                    ->label('Risk Event')
                    ->limit(70)
                    ->wrap(),

                Tables\Columns\TextColumn::make('c_risk_status')
                    ->label('Status')
                    ->sortable()
                    ->wrap()
                    ->lineClamp(3)
                    ->extraAttributes([
                        'class' => 'whitespace-normal break-words max-w-sm',
                        'style' => 'white-space: normal !important;',
                    ])
                    ->formatStateUsing(fn ($state, Tmrisk $record) => $record->statusLabelWithActor()),

                Tables\Columns\TextColumn::make('updated_at_sort')
                    ->label('Updated At')
                    ->sortable(true, function (Builder $query, string $direction) use ($riskTable) {
                        return $query
                            ->reorder()
                            ->orderBy('updated_at_sort', $direction)
                            ->orderByDesc($riskTable . '.i_id_risk');
                    })
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '';

                        try {
                            $dt = $state instanceof Carbon ? $state : Carbon::parse($state);
                            return $dt->format('Y-m-d') . '<br>' . $dt->format('H:i:s');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    })
                    ->html(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('view')
                        ->label('View')
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (Tmrisk $record) => RiskResource::getUrl('view', ['record' => $record]) . '?from=approval')
                        ->openUrlInNewTab(false),

                    Actions\Action::make('request_delete')
                        ->label('Request Delete')
                        ->color('danger')
                        ->icon(Heroicon::OutlinedTrash)
                        ->visible(fn (Tmrisk $record) =>
                            RiskApprovalWorkflow::canRequestDeleteForCurrentUser((int) $record->c_risk_status)
                        )
                        ->url(fn (Tmrisk $record) =>
                            RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=delete'
                        )
                        ->openUrlInNewTab(false),

                    Actions\Action::make('approve')
                        ->label(fn (Tmrisk $record) => ((int) $record->c_risk_status === 5) ? 'Approve Delete' : 'Approve')
                        ->color('success')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn (Tmrisk $record) =>
                            RiskApprovalWorkflow::canApproveStatusForCurrentUser((int) $record->c_risk_status)
                        )
                        ->url(fn (Tmrisk $record) =>
                            RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=approve'
                        )
                        ->openUrlInNewTab(false),

                    Actions\Action::make('reject')
                        ->label('Reject')
                        ->color('danger')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->visible(fn (Tmrisk $record) =>
                            RiskApprovalWorkflow::canRejectStatusForCurrentUser((int) $record->c_risk_status)
                        )
                        ->url(fn (Tmrisk $record) =>
                            RiskApprovalResource::getUrl('create') . '?risk=' . $record->getKey() . '&decision=reject'
                        )
                        ->openUrlInNewTab(false),
                ])
                    ->icon(Heroicon::OutlinedBars3)
                    ->label('')
                    ->tooltip('Actions'),
            ]);
    }
}
