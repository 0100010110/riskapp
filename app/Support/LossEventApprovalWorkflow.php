<?php

namespace App\Support;

use App\Models\Tmlostevent;
use App\Services\EmployeeCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LossEventApprovalWorkflow
{
    public const STATUS_DRAFT            = 0;
    public const STATUS_DELETE_REQUEST   = 5;

    public const STATUS_APPROVED_OFFICER = 14;
    public const STATUS_APPROVED_KADIV   = 15;
    public const STATUS_PENGAJUAN_ADMIN  = 16;
    public const STATUS_APPROVED_FINAL   = 17;

    // ✅ session key khusus Loss Event Approval
    public const SESSION_SIM_KEY = 'loss_event_approval.simulate';

    /** @var array<string,mixed>|null */
    protected static ?array $cachedContext = null;

    public static function flushContext(): void
    {
        static::$cachedContext = null;
    }

    /**
     * Simulate state:
     * @return array{role_type?:string, org_prefix?:string}
     */
    public static function getSimulateState(): array
    {
        try {
            $v = session()->get(self::SESSION_SIM_KEY, []);
            return is_array($v) ? $v : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function setSimulateState(string $roleType, ?string $orgPrefix = null): void
    {
        $roleType  = strtolower(trim($roleType));
        $orgPrefix = static::normalizeOrgPrefix($orgPrefix);

        $allowed = [
            RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN,
            RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY,
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
            RiskApprovalWorkflow::ROLE_TYPE_KADIV,
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ];

        if (! in_array($roleType, $allowed, true)) {
            $roleType = RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN;
        }

        // admin/approval GRC selalu GR
        if (in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ], true)) {
            $orgPrefix = 'GR';
        }

        // kalau pilih superadmin -> lebih aman: clear simulate
        if ($roleType === RiskApprovalWorkflow::ROLE_TYPE_SUPERADMIN) {
            static::clearSimulateState();
            return;
        }

        try {
            session()->put(self::SESSION_SIM_KEY, [
                'role_type'  => $roleType,
                'org_prefix' => $orgPrefix,
            ]);
        } catch (\Throwable) {
        }

        static::flushContext();
    }

    public static function clearSimulateState(): void
    {
        try {
            session()->forget(self::SESSION_SIM_KEY);
        } catch (\Throwable) {
        }

        static::flushContext();
    }

    /**
     * Context Loss Event Approval:
     * - default pakai RiskApprovalWorkflow::context()
     * - jika REAL superadmin, boleh override (masking) dari session loss_event_approval.simulate
     */
    public static function context(): array
    {
        if (static::$cachedContext !== null) {
            return static::$cachedContext;
        }

        $base = RiskApprovalWorkflow::context();

        // ✅ FIX UTAMA: pakai checker real superadmin yang pasti ada
        if (! RiskApprovalWorkflow::isRealSuperadmin()) {
            return static::$cachedContext = $base;
        }

        $sim = static::getSimulateState();
        $simRole = strtolower(trim((string) ($sim['role_type'] ?? '')));

        if ($simRole === '') {
            return static::$cachedContext = $base;
        }

        $allowed = [
            RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY,
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
            RiskApprovalWorkflow::ROLE_TYPE_KADIV,
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ];

        if (! in_array($simRole, $allowed, true)) {
            return static::$cachedContext = $base;
        }

        $simDiv = static::normalizeOrgPrefix((string) ($sim['org_prefix'] ?? ''));

        if (in_array($simRole, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ], true)) {
            $simDiv = 'GR';
        }

        // override: seolah-olah bukan superadmin (agar scope jalan sesuai role)
        $base['is_superadmin'] = false;
        $base['impersonating'] = true;
        $base['role_type']     = $simRole;
        $base['org_prefix']    = $simDiv !== '' ? $simDiv : (string) ($base['org_prefix'] ?? '');

        return static::$cachedContext = $base;
    }

    public static function isRealSuperadmin(): bool
    {
        return RiskApprovalWorkflow::isRealSuperadmin();
    }

    public static function roleType(): string
    {
        return (string) (static::context()['role_type'] ?? '');
    }

    public static function isSuper(): bool
    {
        return (bool) (static::context()['is_superadmin'] ?? false);
    }

    public static function currentUserId(): int
    {
        return (int) (static::context()['user_id'] ?? 0);
    }

    public static function currentUserOrgPrefix(): string
    {
        return (string) (static::context()['org_prefix'] ?? '');
    }

    /** Scope untuk menu Loss Event biasa (lihat data sesuai role) */
    public static function applyLossEventEntryScope(Builder $query): Builder
    {
        $ctx = static::context();

        if (! empty($ctx['is_superadmin'])) {
            return $query;
        }

        $role = (string) ($ctx['role_type'] ?? '');
        $uid  = (int) ($ctx['user_id'] ?? 0);
        $org  = (string) ($ctx['org_prefix'] ?? '');

        return match ($role) {
            RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY => $query->where('i_entry', $uid),

            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
            RiskApprovalWorkflow::ROLE_TYPE_KADIV => $org !== ''
                ? $query->whereIn('i_entry', static::employeeIdsForOrgPrefix($org))
                : $query->whereRaw('1=0'),

            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC => $query,

            default => $query->whereRaw('1=0'),
        };
    }

    /** Scope list approval: status actionable OR kontribusi */
    public static function applyApprovalListScope(Builder $query): Builder
    {
        $ctx = static::context();

        if (! empty($ctx['is_superadmin'])) {
            return $query;
        }

        $role = (string) ($ctx['role_type'] ?? '');
        $org  = (string) ($ctx['org_prefix'] ?? '');
        $uid  = (int) ($ctx['user_id'] ?? 0);

        $tbl = (new Tmlostevent())->getTable();

        // base visibility (RSA: sendiri, Officer/Kadiv: divisi, GRC: semua)
        $query = match ($role) {
            RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY => $query->where("{$tbl}.i_entry", $uid),

            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
            RiskApprovalWorkflow::ROLE_TYPE_KADIV => $org !== ''
                ? $query->whereIn("{$tbl}.i_entry", static::employeeIdsForOrgPrefix($org))
                : $query->whereRaw('1=0'),

            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC => $query,

            default => $query->whereRaw('1=0'),
        };

        $actionable = static::actionableStatusesForCurrentUser();

        return $query->where(function (Builder $w) use ($actionable, $uid, $tbl) {
            // ✅ actionable statuses
            if (! empty($actionable)) {
                $w->whereIn("{$tbl}.c_lostevent_status", $actionable);
            } else {
                $w->whereRaw('1=0');
            }

            // ✅ kontribusi via activity_log (FIX: log_name Lostevent + exclude created)
            if ($uid > 0) {
                $w->orWhereExists(function ($sq) use ($uid, $tbl) {
                    $sq->select(DB::raw(1))
                        ->from('activity_log as al')
                        ->whereColumn('al.subject_id', "{$tbl}.i_id_lostevent")
                        ->where(function ($x) {
                            // utama: subject_type
                            $x->where('al.subject_type', Tmlostevent::class)
                              // fallback: log_name (di log kamu: "Lostevent")
                              ->orWhereRaw('LOWER(al.log_name) IN (?, ?)', ['lostevent', 'tmlostevent']);
                        })
                        ->where('al.causer_id', $uid)
                        // ⛔ jangan anggap "created" sebagai kontribusi
                        ->where(function ($x) {
                            $x->whereNull('al.event')
                              ->orWhere('al.event', '!=', 'created');
                        });
                });
            }
        });
    }

    public static function actionableStatusesForCurrentUser(): array
    {
        if (static::isSuper()) {
            return [0, 14, 15, 16, 5, 17];
        }

        return match (static::roleType()) {
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER => [0],
            RiskApprovalWorkflow::ROLE_TYPE_KADIV        => [14],
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC    => [15],
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC => [16, 5],
            default => [],
        };
    }

    public static function nextStatusOnApproveForCurrentUser(int $status): ?int
    {
        if ($status === static::STATUS_DELETE_REQUEST) {
            return null;
        }

        if (static::isSuper()) {
            return match ($status) {
                0  => 14,
                14 => 15,
                15 => 16,
                16 => 17,
                default => null,
            };
        }

        return match (static::roleType()) {
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER => ($status === 0)  ? 14 : null,
            RiskApprovalWorkflow::ROLE_TYPE_KADIV        => ($status === 14) ? 15 : null,
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC    => ($status === 15) ? 16 : null,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC => ($status === 16) ? 17 : null,
            default => null,
        };
    }

    public static function nextStatusOnRejectForCurrentUser(int $status): ?int
    {
        if (static::isSuper()) {
            return match ($status) {
                0  => 0,
                14 => 0,
                15 => 14,
                16 => 15,
                5  => 15,
                17 => 16,
                default => null,
            };
        }

        return match (static::roleType()) {
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER => ($status === 0)  ? 0  : null,
            RiskApprovalWorkflow::ROLE_TYPE_KADIV        => ($status === 14) ? 0  : null,
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC    => ($status === 15) ? 14 : null,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC => match ($status) {
                16 => 15,
                5  => 15,
                default => null,
            },
            default => null,
        };
    }

    public static function canRequestDeleteForCurrentUser(int $status): bool
    {
        if (static::isSuper()) {
            return $status === 15;
        }

        return static::roleType() === RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC && $status === 15;
    }

    public static function canApproveDeleteRequestForCurrentUser(int $status): bool
    {
        if ($status !== static::STATUS_DELETE_REQUEST) return false;

        if (static::isSuper()) return true;

        return static::roleType() === RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC;
    }

    public static function canApproveStatusForCurrentUser(int $status): bool
    {
        if ($status === static::STATUS_DELETE_REQUEST) {
            return static::canApproveDeleteRequestForCurrentUser($status);
        }
        return static::nextStatusOnApproveForCurrentUser($status) !== null;
    }

    public static function canRejectStatusForCurrentUser(int $status): bool
    {
        return static::nextStatusOnRejectForCurrentUser($status) !== null;
    }

    public static function requestDelete(Tmlostevent $record): void
    {
        DB::transaction(function () use ($record) {
            $status = (int) ($record->c_lostevent_status ?? 0);

            if (! static::canRequestDeleteForCurrentUser($status)) {
                throw new \RuntimeException('Tidak diizinkan mengajukan hapus untuk status ini.');
            }

            $uid = static::currentUserId();

            $record->c_lostevent_status = static::STATUS_DELETE_REQUEST;
            $record->i_update = $uid > 0 ? $uid : null;
            $record->d_update = now();
            $record->save();
        });
    }

    public static function approve(Tmlostevent $record): void
    {
        DB::transaction(function () use ($record) {
            $status = (int) ($record->c_lostevent_status ?? 0);

            if ($status === static::STATUS_DELETE_REQUEST) {
                if (! static::canApproveDeleteRequestForCurrentUser($status)) {
                    throw new \RuntimeException('Tidak diizinkan approve pengajuan hapus.');
                }

                $uid = static::currentUserId();
                $record->i_update = $uid > 0 ? $uid : null;
                $record->d_update = now();
                $record->save();

                $record->delete();
                return;
            }

            $next = static::nextStatusOnApproveForCurrentUser($status);
            if ($next === null) {
                throw new \RuntimeException('Status tidak bisa di-approve dari kondisi saat ini.');
            }

            $uid = static::currentUserId();

            $record->c_lostevent_status = $next;
            $record->i_update = $uid > 0 ? $uid : null;
            $record->d_update = now();
            $record->save();
        });
    }

    public static function reject(Tmlostevent $record): void
    {
        DB::transaction(function () use ($record) {
            $status = (int) ($record->c_lostevent_status ?? 0);

            $next = static::nextStatusOnRejectForCurrentUser($status);
            if ($next === null) {
                throw new \RuntimeException('Status tidak bisa di-reject dari kondisi saat ini.');
            }

            $uid = static::currentUserId();

            $record->c_lostevent_status = $next;
            $record->i_update = $uid > 0 ? $uid : null;
            $record->d_update = now();
            $record->save();
        });
    }

    public static function groupKeyForRecord(Tmlostevent $record): string
    {
        $year = $record->d_lost_event ? (string) $record->d_lost_event->format('Y') : '-';

        $role = static::roleType();
        $isYearDivision = in_array($role, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ], true);

        if (! $isYearDivision) {
            return $year;
        }

        $div = static::divisionPrefixByEntryUser((int) ($record->i_entry ?? 0));
        $div = $div !== '' ? $div : '-';

        return $year . '|' . $div;
    }

    public static function groupTitleForRecord(Tmlostevent $record): string
    {
        $key = static::groupKeyForRecord($record);

        if (str_contains($key, '|')) {
            [$year, $div] = array_pad(explode('|', $key, 2), 2, '-');
            $year = trim((string) $year) ?: '-';
            $div  = trim((string) $div)  ?: '-';
            return "{$year} — Divisi: {$div}";
        }

        return $key;
    }

    private static function divisionPrefixByEntryUser(int $entryUserId): string
    {
        if ($entryUserId <= 0) return '';

        try {
            $svc = app(EmployeeCacheService::class);
            $row = $svc->findById($entryUserId);

            $org = is_array($row)
                ? trim((string) ($row['organisasi'] ?? $row['organization'] ?? $row['org'] ?? ''))
                : '';

            return static::normalizeOrgPrefix($org);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function employeeIdsForOrgPrefix(string $prefix): array
    {
        $prefix = static::normalizeOrgPrefix($prefix);
        if ($prefix === '') return [];

        try {
            $svc = app(EmployeeCacheService::class);
            $data = $svc->data();

            $ids = [];

            foreach ($data as $r) {
                if (! is_array($r)) continue;

                $org = trim((string) ($r['organisasi'] ?? $r['organization'] ?? $r['org'] ?? ''));
                if (static::normalizeOrgPrefix($org) !== $prefix) continue;

                $id = (int) ($r['id'] ?? 0);
                if ($id <= 0) {
                    $id = (int) preg_replace('/\D+/', '', (string) ($r['nik'] ?? ''));
                }

                if ($id > 0) $ids[] = $id;
            }

            return array_values(array_unique($ids));
        } catch (\Throwable) {
            return [];
        }
    }

    private static function normalizeOrgPrefix(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';

        if (preg_match('/^([A-Za-z]{2})/', $value, $m)) {
            return strtoupper($m[1]);
        }

        return strtoupper(substr($value, 0, 2));
    }
}
