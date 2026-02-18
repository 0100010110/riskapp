<?php

namespace App\Filament\Resources\RiskMitigations\Tables;

use App\Filament\Resources\RiskMitigations\RiskMitigationResource;
use App\Support\RiskApprovalWorkflow;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

class RiskMitigationsTable
{
    protected static array $monthNames = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    protected static function applyScope(Builder $query): Builder
    {
        $ctx      = RiskApprovalWorkflow::context();
        $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);
        $roleType = (string) ($ctx['role_type'] ?? '');
        $org      = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));
        $uid      = (int) ($ctx['user_id'] ?? 0);

        if ($isSuper) {
            return $query;
        }

        if (in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC ?? 'admin_grc',
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC ?? 'approval_grc',
            RiskApprovalWorkflow::ROLE_TYPE_GRC ?? 'grc',
        ], true)) {
            return $query;
        }

        if (in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER ?? 'risk_officer',
            RiskApprovalWorkflow::ROLE_TYPE_OFFICER ?? 'officer',
            RiskApprovalWorkflow::ROLE_TYPE_KADIV ?? 'kadiv',
        ], true)) {
            if ($org === '') {
                return $query->whereRaw('1=0');
            }
            return $query->whereHas('riskInherent.risk', fn (Builder $q) => $q->where('c_org_owner', $org));
        }

        if (($roleType === (RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY ?? 'rsa_entry')) && $uid > 0) {
            return $query->where('i_entry', $uid);
        }

        return $query->whereRaw('1=0');
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with(['riskInherent.risk']);

                return self::applyScope($query);
            })

            ->columns([
                Tables\Columns\TextColumn::make('riskInherent.risk.c_risk_year')
                    ->label('Year')
                    ->sortable(),

                Tables\Columns\TextColumn::make('riskInherent.risk.i_risk')
                    ->label('Risk No')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('riskInherent.i_risk_inherent')
                    ->label('Risk Inherent')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('riskInherent.risk.e_risk_event')
                    ->label('Risk Event')
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\TextColumn::make('e_risk_mitigation')
                    ->label('Mitigation')
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\TextColumn::make('c_org_mitigation')
                    ->label('PIC')
                    ->wrap(),

                Tables\Columns\TextColumn::make('v_mitigation_cost')
                    ->label('Cost')
                    ->money('USD', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('f_mitigation_month')
                    ->label('Months')
                    ->wrap()
                    ->formatStateUsing(function ($state) {
                        $months = self::monthsFromBinary((string) $state);

                        if (empty($months)) {
                            return '-';
                        }

                        return self::formatMonthRanges($months);
                    }),

                Tables\Columns\TextColumn::make('i_entry')->label('Created By'),
                Tables\Columns\TextColumn::make('d_entry')->label('Created At')->dateTime(),
            ])

            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => RiskMitigationResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => RiskMitigationResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Record masih direferensikan tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn ($record) =>
                        RiskMitigationResource::canEdit($record) || RiskMitigationResource::canDelete($record)
                    ),
            ])

            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => RiskMitigationResource::canDeleteAny())
                        ->action(function ($records) {
                            try {
                                $records->each->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Sebagian data masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ]),
            ])

            ->defaultSort('i_id_riskmitigation', 'desc');
    }

    private static function normalizeBinary(string $bin): string
    {
        $bin = preg_replace('/[^01]/', '', $bin) ?? '';
        $bin = substr($bin, 0, 12);
        return str_pad($bin, 12, '0', STR_PAD_RIGHT);
    }

    private static function monthsFromBinary(string $bin): array
    {
        $bin = self::normalizeBinary($bin);

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            if (($bin[$i] ?? '0') === '1') {
                $months[] = $i + 1;
            }
        }

        return $months;
    }

    private static function formatMonthRanges(array $months): string
    {
        sort($months);

        $ranges = [];
        $start = $months[0];
        $prev  = $months[0];

        for ($i = 1; $i < count($months); $i++) {
            $cur = $months[$i];

            if ($cur === $prev + 1) {
                $prev = $cur;
                continue;
            }

            $ranges[] = [$start, $prev];
            $start = $prev = $cur;
        }

        $ranges[] = [$start, $prev];

        $parts = [];
        foreach ($ranges as [$a, $b]) {
            $na = self::$monthNames[$a] ?? (string) $a;
            $nb = self::$monthNames[$b] ?? (string) $b;

            $parts[] = ($a === $b) ? $na : ($na . ' - ' . $nb);
        }

        return implode(', ', $parts);
    }
}
