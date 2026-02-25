<?php

namespace App\Support;

use App\Models\Trrole;
use App\Models\Truserrole;
use App\Policies\SuperadminPolicy;
use App\Services\EmployeeCacheService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Filament\Resources\Risks\RiskResource;
use App\Models\Tmrisk;
use App\Services\RolePermissionService;
use App\Support\PermissionBitmask;

class RiskApprovalWorkflow
{
    public const ROLE_TYPE_RSA_ENTRY    = 'rsa_entry';
    public const ROLE_TYPE_RISK_OFFICER = 'risk_officer';
    public const ROLE_TYPE_KADIV        = 'kadiv';
    public const ROLE_TYPE_ADMIN_GRC    = 'admin_grc';
    public const ROLE_TYPE_APPROVAL_GRC = 'approval_grc';

    public const ROLE_TYPE_SUPERADMIN   = 'superadmin';

    public const ROLE_TYPE_OFFICER = self::ROLE_TYPE_RISK_OFFICER;
    public const ROLE_TYPE_GRC     = self::ROLE_TYPE_ADMIN_GRC;

    /** @var array<string,mixed>|null */
    protected static ?array $cachedContext = null;

    /** @var array<string, array>|null */
    protected static ?array $employeeNikIndex = null;

    private const SESSION_KEY_SIM = 'risk_approval.simulate';

    public static function flushContext(): void
    {
        static::$cachedContext = null;
        static::$employeeNikIndex = null;
    }

    public static function isRealSuperadmin(): bool
    {
        $ctx = static::context();
        return (bool) ($ctx['is_superadmin_real'] ?? false);
    }

    public static function simulateRoleOptions(): array
    {
        return [
            self::ROLE_TYPE_SUPERADMIN   => 'Superadmin',
            self::ROLE_TYPE_RSA_ENTRY    => 'RSA Entry',
            self::ROLE_TYPE_RISK_OFFICER => 'Risk Officer',
            self::ROLE_TYPE_KADIV        => 'Kepala Divisi / Kadiv',
            self::ROLE_TYPE_ADMIN_GRC    => 'Admin GRC',
            self::ROLE_TYPE_APPROVAL_GRC => 'Approval GRC',
        ];
    }

    /**
     * @return array{role_type?:string, org_prefix?:string}
     */
    public static function getSimulateState(): array
    {
        try {
            $v = session()->get(self::SESSION_KEY_SIM, []);
            return is_array($v) ? $v : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function setSimulateState(string $roleType, ?string $orgPrefix = null): void
    {
        $roleType = trim(strtolower($roleType));

        $allowed = array_keys(self::simulateRoleOptions());
        if (! in_array($roleType, $allowed, true)) {
            $roleType = self::ROLE_TYPE_SUPERADMIN;
        }

        $orgPrefix = self::normalizeOrgPrefix($orgPrefix);

        if (in_array($roleType, [self::ROLE_TYPE_ADMIN_GRC, self::ROLE_TYPE_APPROVAL_GRC], true)) {
            $orgPrefix = 'GR';
        }

        try {
            session()->put(self::SESSION_KEY_SIM, [
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
            session()->forget(self::SESSION_KEY_SIM);
        } catch (\Throwable) {
        }

        static::flushContext();
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

    private static function resolveSuperadminRoleIdByString(): ?int
    {
        $row = Trrole::query()
            ->select(['i_id_role', 'c_role', 'n_role'])
            ->where('f_active', true)
            ->where(function (Builder $b) {
                $b->whereRaw("LOWER(COALESCE(c_role,'')) LIKE ?", ['%superadmin%'])
                  ->orWhereRaw("LOWER(COALESCE(n_role,'')) LIKE ?", ['%superadmin%']);
            })
            ->orderByRaw("
                CASE
                    WHEN LOWER(COALESCE(n_role,'')) = 'superadmin' OR LOWER(COALESCE(c_role,'')) = 'superadmin' THEN 0
                    ELSE 1
                END
            ")
            ->orderBy('i_id_role', 'asc')
            ->first();

        return $row ? (int) $row->i_id_role : null;
    }

    /**
     * @return array{
     *   user:mixed,
     *   user_id:int,
     *   is_superadmin_real:bool,
     *   is_superadmin:bool,
     *   impersonating:bool,
     *   role_type:?string,
     *   role_id:?int,
     *   org_prefix:string
     * }
     */
    public static function context(): array
    {
        if (static::$cachedContext !== null) {
            return static::$cachedContext;
        }

        $user = null;
        try {
            $user = Filament::auth()->user();
        } catch (\Throwable) {
            $user = null;
        }
        $user ??= auth()->user();

        $userId = (int) ($user?->getAuthIdentifier() ?? 0);

        // ✅ Real superadmin sekarang ditentukan oleh policy
        $isSuperAdminReal = false;
        try {
            $isSuperAdminReal = SuperadminPolicy::isSuperadmin($user);
        } catch (\Throwable) {
            $isSuperAdminReal = false;
        }

        $roleType = null;
        $roleId   = null;

        $orgPrefixReal = static::resolveUserOrgPrefix($user, $userId);

        $superadminRoleId = null;
        if ($isSuperAdminReal) {
            $superadminRoleId = static::resolveSuperadminRoleIdByString();
            $roleId = $superadminRoleId;
        }

        if (! $isSuperAdminReal) {
            $roleIds = static::roleIdsForUser($user);

            if (! empty($roleIds)) {
                $roles = Trrole::query()
                    ->select(['i_id_role', 'c_role', 'n_role'])
                    ->whereIn('i_id_role', $roleIds)
                    ->where('f_active', true)
                    ->get();

                foreach ($roles as $r) {
                    if (static::roleLooksLikeApprovalGrc($r->c_role, $r->n_role)) {
                        $roleType = static::ROLE_TYPE_APPROVAL_GRC;
                        $roleId = (int) $r->i_id_role;
                        break;
                    }
                }

                if (! $roleType) {
                    foreach ($roles as $r) {
                        if (static::roleLooksLikeAdminGrc($r->c_role, $r->n_role)) {
                            $roleType = static::ROLE_TYPE_ADMIN_GRC;
                            $roleId = (int) $r->i_id_role;
                            break;
                        }
                    }
                }

                if (! $roleType) {
                    foreach ($roles as $r) {
                        if (static::roleLooksLikeKadiv($r->c_role, $r->n_role)) {
                            $roleType = static::ROLE_TYPE_KADIV;
                            $roleId = (int) $r->i_id_role;
                            break;
                        }
                    }
                }

                if (! $roleType) {
                    foreach ($roles as $r) {
                        if (static::roleLooksLikeRiskOfficer($r->c_role, $r->n_role)) {
                            $roleType = static::ROLE_TYPE_RISK_OFFICER;
                            $roleId = (int) $r->i_id_role;
                            break;
                        }
                    }
                }

                if (! $roleType) {
                    foreach ($roles as $r) {
                        if (static::roleLooksLikeRsaEntry($r->c_role, $r->n_role)) {
                            $roleType = static::ROLE_TYPE_RSA_ENTRY;
                            $roleId = (int) $r->i_id_role;
                            break;
                        }
                    }
                }
            }
        }

        $impersonating = false;
        $effectiveIsSuper = (bool) $isSuperAdminReal;
        $effectiveRoleType = $isSuperAdminReal ? null : $roleType;
        $effectiveOrgPrefix = $orgPrefixReal;

        // ✅ Simulasi hanya berlaku untuk real superadmin (policy)
        if ($isSuperAdminReal) {
            $sim = static::getSimulateState();
            $simRole = strtolower(trim((string) ($sim['role_type'] ?? self::ROLE_TYPE_SUPERADMIN)));

            if ($simRole === '' || $simRole === self::ROLE_TYPE_SUPERADMIN) {
                $impersonating = false;
                $effectiveIsSuper = true;
                $effectiveRoleType = null;
                $effectiveOrgPrefix = $orgPrefixReal;
                $roleId = $superadminRoleId;
            } else {
                $allowed = array_keys(static::simulateRoleOptions());
                if (! in_array($simRole, $allowed, true)) {
                    $simRole = self::ROLE_TYPE_SUPERADMIN;
                }

                if ($simRole !== self::ROLE_TYPE_SUPERADMIN) {
                    $impersonating = true;
                    $effectiveIsSuper = false;
                    $effectiveRoleType = $simRole;

                    $simDiv = static::normalizeOrgPrefix((string) ($sim['org_prefix'] ?? ''));

                    if (in_array($simRole, [self::ROLE_TYPE_ADMIN_GRC, self::ROLE_TYPE_APPROVAL_GRC], true)) {
                        $simDiv = 'GR';
                    }

                    $effectiveOrgPrefix = $simDiv !== '' ? $simDiv : $orgPrefixReal;

                    $roleId = $superadminRoleId;
                } else {
                    $impersonating = false;
                    $effectiveIsSuper = true;
                    $effectiveRoleType = null;
                    $effectiveOrgPrefix = $orgPrefixReal;
                    $roleId = $superadminRoleId;
                }
            }
        }

        return static::$cachedContext = [
            'user'               => $user,
            'user_id'            => $userId,

            'is_superadmin_real' => (bool) $isSuperAdminReal,

            'is_superadmin'      => (bool) $effectiveIsSuper,
            'impersonating'      => (bool) $impersonating,
            'role_type'          => $effectiveRoleType,
            'role_id'            => $roleId,
            'org_prefix'         => (string) $effectiveOrgPrefix,
        ];
    }

    /**
     * @return array<int>
     */
    private static function roleIdsForUser($user): array
    {
        $uid = (int) ($user?->getAuthIdentifier() ?? 0);

        $ids = Truserrole::query()
            ->where('i_id_user', $uid)
            ->pluck('i_id_role')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if (! empty($ids)) {
            return $ids;
        }

        $nikRaw = trim((string) ($user?->nik ?? ''));
        if ($nikRaw !== '' && ctype_digit($nikRaw)) {
            $nik = (int) $nikRaw;
            if ($nik > 0 && $nik !== $uid) {
                $ids = Truserrole::query()
                    ->where('i_id_user', $nik)
                    ->pluck('i_id_role')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($v) => $v > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return $ids;
    }

    private static function resolveEmployeeRow(EmployeeCacheService $svc, int $userId, ?string $nik): ?array
    {
        if ($userId > 0) {
            try {
                $row = $svc->findById($userId);
                if (is_array($row)) {
                    return $row;
                }
            } catch (\Throwable) {
            }
        }

        $nik = trim((string) $nik);
        if ($nik === '') {
            return null;
        }

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

    private static function resolveUserOrgPrefix($user, int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        try {
            $svc = app(EmployeeCacheService::class);
            $nik = trim((string) ($user?->nik ?? ''));

            $row = static::resolveEmployeeRow($svc, $userId, $nik);

            $org = is_array($row)
                ? trim((string) ($row['organisasi'] ?? $row['organization'] ?? $row['org'] ?? ''))
                : '';

            if ($org === '') {
                return '';
            }

            return self::normalizeOrgPrefix($org);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function currentApproverRoleId(): ?int
    {
        $ctx = static::context();
        return $ctx['role_id'] ? (int) $ctx['role_id'] : null;
    }

    public static function currentUserNik(): string
    {
        $ctx = static::context();
        $user = $ctx['user'];

        $nik = trim((string) ($user?->nik ?? ''));
        return $nik !== '' ? $nik : (string) ((int) ($ctx['user_id'] ?? 0));
    }

    public static function currentEmpId(): int
    {
        $ctx = static::context();
        if (! empty($ctx['impersonating'])) {
            return 0;
        }

        $nik = static::currentUserNik();
        $empId = (int) preg_replace('/\D+/', '', (string) $nik);
        return $empId > 0 ? $empId : 0;
    }

    public static function currentUserName(): string
    {
        $ctx = static::context();
        $user = $ctx['user'];

        $name = trim((string) ($user?->name ?? $user?->n_name ?? ''));
        return $name !== '' ? $name : 'Unknown';
    }

    public static function currentUserOrgPrefix(): string
    {
        return (string) (static::context()['org_prefix'] ?? '');
    }

    public static function decisionFromRequest(): string
    {
        try {
            $d = strtolower(trim((string) request()->query('decision', 'approve')));
        } catch (\Throwable) {
            $d = 'approve';
        }

        return in_array($d, ['approve', 'reject', 'delete'], true) ? $d : 'approve';
    }

    public static function decisionIsReject(): bool
    {
        return static::decisionFromRequest() === 'reject';
    }

    public static function decisionIsDelete(): bool
    {
        return static::decisionFromRequest() === 'delete';
    }

    public static function applyRiskRegisterScope(Builder $query): Builder
    {
        $ctx = static::context();

        if ($ctx['is_superadmin']) {
            return $query;
        }

        return match ($ctx['role_type']) {
            static::ROLE_TYPE_RSA_ENTRY => $query->where('i_entry', (int) ($ctx['user_id'] ?? 0)),

            static::ROLE_TYPE_RISK_OFFICER,
            static::ROLE_TYPE_KADIV => ($ctx['org_prefix'] ?? '') !== ''
                ? $query->where('c_org_owner', (string) $ctx['org_prefix'])
                : $query->whereRaw('1=0'),

            static::ROLE_TYPE_ADMIN_GRC,
            static::ROLE_TYPE_APPROVAL_GRC => $query,

            default => $query->whereRaw('1=0'),
        };
    }

    public static function applyApprovalListScope(Builder $query): Builder
    {
        $ctx = static::context();

        if ($ctx['is_superadmin']) {
            return $query;
        }

        return match ($ctx['role_type']) {
            static::ROLE_TYPE_KADIV,
            static::ROLE_TYPE_RISK_OFFICER => ($ctx['org_prefix'] ?? '') !== ''
                ? $query->where('c_org_owner', (string) $ctx['org_prefix'])
                : $query->whereRaw('1=0'),

            static::ROLE_TYPE_ADMIN_GRC,
            static::ROLE_TYPE_APPROVAL_GRC => $query,

            default => $query->whereRaw('1=0'),
        };
    }

    // ... (SISA METHOD DI BAWAH INI TETAP SAMA PERSIS DENGAN KODE KAMU)
    // NOTE: demi ringkas, kamu tinggal copy-paste sisa isi file dari versi kamu
    // mulai dari actionableStatusesForCurrentUser() sampai canEditRiskOnApproval().
    // Tidak ada perubahan yang diperlukan selain blok "superadmin real" yang sudah diganti policy di atas.

    // ⛔️ Kalau kamu butuh, bilang “lanjutkan sisa file RiskApprovalWorkflow” dan aku tempel full sampai bawah.
}