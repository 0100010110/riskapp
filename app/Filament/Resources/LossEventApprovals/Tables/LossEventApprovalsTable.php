<?php

namespace App\Filament\Resources\LossEventApprovals\Tables;

use App\Models\Tmlostevent;
use App\Services\EmployeeCacheService;
use App\Support\LossEventApprovalWorkflow;
use App\Support\TaxonomyFormatter;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;

class LossEventApprovalsTable
{
    /** @var array<int,string> cache userId => orgPrefix */
    protected static array $creatorOrgPrefixCache = [];

    /** @var array<string, array>|null cache nik(string) => employeeRow */
    protected static ?array $employeeNikIndex = null;

    public static function configure(Table $table): Table
    {
        $lostTable = (new Tmlostevent())->getTable();

        return $table
            ->query(function () use ($lostTable): Builder {
                // ✅ apply scope approval sesuai role (termasuk simulasi/masking)
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

            // ✅ Hilangkan klik row => edit/view
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])

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
                        if (! $state) return '';
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

                Tables\Columns\TextColumn::make('c_lostevent_status')
                    ->label('Status')
                    ->sortable()
                    ->formatStateUsing(function ($state, Tmlostevent $record): string {
                        return method_exists($record, 'statusLabelWithActor')
                            ? (string) $record->statusLabelWithActor()
                            : (string) ((int) ($state ?? 0));
                    }),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Submitted At')
                    ->sortable()
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
        if ($nik === '') return null;

        if (static::$employeeNikIndex === null) {
            static::$employeeNikIndex = [];

            try {
                $data = $svc->data();
                if (is_iterable($data)) {
                    foreach ($data as $r) {
                        if (! is_array($r)) continue;

                        $nk = trim((string) ($r['nik'] ?? ''));
                        if ($nk === '') continue;

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
