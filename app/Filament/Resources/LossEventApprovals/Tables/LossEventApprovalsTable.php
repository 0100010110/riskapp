<?php

namespace App\Filament\Resources\LossEventApprovals\Tables;

use App\Filament\Resources\LossEventApprovals\LossEventApprovalResource;
use App\Models\Tmlostevent;
use App\Services\EmployeeCacheService;
use App\Support\LossEventApprovalWorkflow;
use App\Support\TaxonomyFormatter;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LossEventApprovalsTable
{
    /** @var array<int,string> */
    protected static array $creatorOrgPrefixCache = [];

    /** @var array<string, array>|null */
    protected static ?array $employeeNikIndex = null;

    public static function configure(Table $table): Table
    {
        $lostTable = (new Tmlostevent())->getTable();

        return $table
            ->query(function () use ($lostTable): Builder {
                $q = Tmlostevent::query();
                $q = LossEventApprovalWorkflow::applyApprovalListScope($q);

                return $q
                    ->select($lostTable . '.*')
                    ->selectRaw("COALESCE(EXTRACT(YEAR FROM {$lostTable}.d_lost_event)::text, '') as approval_group")
                    ->with(['taxonomy'])
                    ->orderByRaw("EXTRACT(YEAR FROM {$lostTable}.d_lost_event) DESC NULLS LAST")
                    ->orderByDesc($lostTable . '.d_lost_event')
                    ->orderByDesc($lostTable . '.i_id_lostevent');
            })
            ->recordUrl(null)
            ->recordAction(null)
            ->groups([
                Group::make('approval_group')
                    ->label('Group')
                    ->collapsible()
                    ->getKeyFromRecordUsing(function (Tmlostevent $record): string {
                        $year = trim((string) ($record->approval_group ?? ''));
                        $year = $year !== '' ? $year : '-';

                        $creatorId = (int) ($record->i_entry ?? 0);
                        $div = self::creatorOrgPrefix($creatorId);
                        $div = $div !== '' ? $div : '-';

                        return $year . '|' . $div;
                    })
                    ->getTitleFromRecordUsing(function (Tmlostevent $record): string {
                        $year = trim((string) ($record->approval_group ?? ''));
                        $year = $year !== '' ? $year : '-';

                        $creatorId = (int) ($record->i_entry ?? 0);
                        $div = self::creatorOrgPrefix($creatorId);
                        $div = $div !== '' ? $div : '-';

                        return "{$year} — Divisi: {$div}";
                    }),
            ])
            ->defaultGroup('approval_group')
            ->columns([
                Tables\Columns\TextColumn::make('taxonomy.c_taxonomy')
                    ->label('Taxonomy Code')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state, Tmlostevent $record): string {
                        return TaxonomyFormatter::formatCode(
                            (string) $state,
                            (int) ($record->taxonomy?->c_taxonomy_level ?? 0),
                        );
                    }),

                Tables\Columns\TextColumn::make('d_lost_event')
                    ->label('Event Date')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '';
                        }

                        try {
                            $dt = $state instanceof Carbon ? $state : Carbon::parse($state);
                            return $dt->format('Y-m-d');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    }),

                Tables\Columns\TextColumn::make('v_lost_event')
                    ->label('Kerugian')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state): string {
                        $v = is_numeric($state) ? (int) $state : null;
                        return $v === null ? '' : number_format($v, 0, '.', ',');
                    }),

                Tables\Columns\TextColumn::make('e_lost_event')
                    ->label('Loss Event')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\ViewColumn::make('status_tracker')
                    ->label('Status')
                    ->view('filament.tables.columns.loss-event-status-tracker')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy($lostTable . '.c_lostevent_status', $direction))
                    ->searchable(query: function (Builder $query, string $search) use ($lostTable): Builder {
                        $needle = strtolower(trim($search));

                        $matchedCodes = collect(Tmlostevent::statusOptions())
                            ->filter(fn (string $label, int $code): bool =>
                                str_contains(strtolower($label), $needle)
                                || str_contains((string) $code, $needle)
                            )
                            ->keys()
                            ->all();

                        return $query->where(function (Builder $q) use ($matchedCodes, $lostTable) {
                            if ($matchedCodes !== []) {
                                $q->whereIn($lostTable . '.c_lostevent_status', $matchedCodes);
                            } else {
                                $q->whereRaw('1 = 0');
                            }
                        });
                    })
                    ->url(null)
                    ->extraAttributes([
                        'class' => 'w-full',
                        'x-on:mousedown.stop.prevent' => '$event.stopPropagation()',
                        'x-on:mouseup.stop.prevent' => '$event.stopPropagation()',
                        'x-on:click.stop.prevent' => '$event.stopPropagation()',
                    ]),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Submitted At')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '';
                        }

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
                    Actions\Action::make('approve')
                        ->label(fn (Tmlostevent $record): string =>
                            ((int) ($record->c_lostevent_status ?? 0) === Tmlostevent::STATUS_DELETE_REQUEST)
                                ? 'Approve Delete'
                                : 'Approve'
                        )
                        ->color('success')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn (Tmlostevent $record): bool =>
                            LossEventApprovalResource::canApproveRecord($record)
                        )
                        ->requiresConfirmation()
                        ->modalHeading(fn (Tmlostevent $record): string =>
                            ((int) ($record->c_lostevent_status ?? 0) === Tmlostevent::STATUS_DELETE_REQUEST)
                                ? 'Approve Delete Loss Event'
                                : 'Approve Loss Event'
                        )
                        ->modalDescription(fn (Tmlostevent $record): string =>
                            ((int) ($record->c_lostevent_status ?? 0) === Tmlostevent::STATUS_DELETE_REQUEST)
                                ? 'Record akan dihapus permanen setelah approval ini. Lanjutkan?'
                                : 'Status loss event akan diproses ke tahap approval berikutnya. Lanjutkan?'
                        )
                        ->action(function (Tmlostevent $record): void {
                            try {
                                LossEventApprovalWorkflow::approve($record);

                                Notification::make()
                                    ->success()
                                    ->title('Approval berhasil diproses')
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Approval gagal diproses')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),

                    Actions\Action::make('delete')
                        ->label('Delete')
                        ->color('danger')
                        ->icon(Heroicon::OutlinedTrash)
                        ->visible(fn (Tmlostevent $record): bool =>
                            LossEventApprovalResource::canDeleteRecord($record)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Delete Loss Event')
                        ->modalDescription('Aksi ini tidak langsung menghapus record. Sistem akan membuat pengajuan penghapusan sesuai flow approval. Lanjutkan?')
                        ->action(function (Tmlostevent $record): void {
                            try {
                                LossEventApprovalWorkflow::requestDelete($record);

                                Notification::make()
                                    ->success()
                                    ->title('Pengajuan hapus berhasil dibuat')
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Pengajuan hapus gagal')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),

                    Actions\Action::make('reject')
                        ->label('Reject')
                        ->color('danger')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->visible(fn (Tmlostevent $record): bool =>
                            LossEventApprovalResource::canRejectRecord($record)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Reject Loss Event')
                        ->modalDescription('Status approval akan dikembalikan ke 1 tingkat sebelumnya. Lanjutkan?')
                        ->action(function (Tmlostevent $record): void {
                            try {
                                LossEventApprovalWorkflow::reject($record);

                                Notification::make()
                                    ->success()
                                    ->title('Reject berhasil diproses')
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Reject gagal diproses')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedBars3)
                    ->label('')
                    ->tooltip('Actions')
                    ->visible(fn (Tmlostevent $record): bool =>
                        LossEventApprovalResource::hasAnyRowAction($record)
                    ),
            ]);
    }

    protected static function creatorOrgPrefix(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        if (array_key_exists($userId, static::$creatorOrgPrefixCache)) {
            return static::$creatorOrgPrefixCache[$userId];
        }

        $prefix = '';

        try {
            $svc = app(EmployeeCacheService::class);

            $row = null;
            try {
                $row = $svc->findById($userId);
            } catch (\Throwable) {
                $row = null;
            }

            if (! is_array($row)) {
                $row = static::employeeRowByNik((string) $userId, $svc);
            }

            $org = is_array($row)
                ? trim((string) ($row['organisasi'] ?? $row['organization'] ?? $row['org'] ?? ''))
                : '';

            if ($org !== '') {
                if (preg_match('/^([A-Za-z]{2})/', $org, $m)) {
                    $prefix = strtoupper($m[1]);
                } else {
                    $prefix = strtoupper(substr($org, 0, 2));
                }
            }
        } catch (\Throwable) {
            $prefix = '';
        }

        static::$creatorOrgPrefixCache[$userId] = $prefix;

        return $prefix;
    }

    protected static function employeeRowByNik(string $nik, EmployeeCacheService $svc): ?array
    {
        $nik = trim($nik);
        if ($nik === '') {
            return null;
        }

        if (static::$employeeNikIndex === null) {
            static::$employeeNikIndex = [];

            try {
                $data = $svc->data();
                if (is_iterable($data)) {
                    foreach ($data as $r) {
                        if (! is_array($r)) {
                            continue;
                        }

                        $nk = trim((string) ($r['nik'] ?? ''));
                        if ($nk === '') {
                            continue;
                        }

                        if (! isset(static::$employeeNikIndex[$nk])) {
                            static::$employeeNikIndex[$nk] = $r;
                        }
                    }
                }
            } catch (\Throwable) {
                static::$employeeNikIndex = [];
            }
        }

        return static::$employeeNikIndex[$nik] ?? null;
    }
}