<?php

namespace App\Support;

use App\Models\Trrole;
use App\Models\Truserrole;
use App\Policies\SuperadminPolicy;
use App\Services\EmployeeCacheService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class RiskWorkflow
{
    public const ROLE_RSA_ENTRY   = 'rsa_entry';
    public const ROLE_RISK_OFFICER = 'risk_officer';
    public const ROLE_KADIV       = 'kadiv';
    public const ROLE_ADMIN_GRC   = 'admin_grc';
    public const ROLE_APPROVAL_GRC = 'approval_grc';
    public const ROLE_UNKNOWN     = 'unknown';

    protected static ?array $cachedContext = null;

    public const STATUS_DRAFT            = 0;
    public const STATUS_APPROVED_STAGE_1 = 4;

    public static function flushContext(): void
    {
        self::$cachedContext = null;
    }

    public static function context(): array
    {
        if (self::$cachedContext !== null) {
            return self::$cachedContext;
        }

        $user = Filament::auth()?->user();
        $userId = (int) ($user?->getAuthIdentifier() ?? 0);

        // ✅ Pindah ke policy (tidak hardcode lagi di sini)
        $isSuperadmin = false;
        try {
            $isSuperadmin = SuperadminPolicy::isSuperadmin($user);
        } catch (\Throwable) {
            $isSuperadmin = false;
        }

        $roleType = self::ROLE_UNKNOWN;
        $roleId = null;

        $roleRows = [];
        try {
            if ($userId > 0) {
                $roleIds = Truserrole::query()
                    ->where('i_id_user', $userId)
                    ->pluck('i_id_role')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($v) => $v > 0)
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($roleIds)) {
                    $roles = Trrole::query()
                        ->whereIn('i_id_role', $roleIds)
                        ->get(['i_id_role', 'c_role', 'n_role']);

                    foreach ($roles as $r) {
                        $roleRows[] = [
                            'i_id_role' => (int) $r->i_id_role,
                            'c_role'    => (string) ($r->c_role ?? ''),
                            'n_role'    => (string) ($r->n_role ?? ''),
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            $roleRows = [];
        }

        $picked = null;
        foreach ($roleRows as $row) {
            $label = trim(strtolower(($row['n_role'] ?: $row['c_role']) ?? ''));
            if ($label === '') continue;

            if (self::roleLooksLikeApprovalGrc($label)) {
                $picked = ['type' => self::ROLE_APPROVAL_GRC, 'id' => $row['i_id_role']];
                break;
            }
        }
        if (! $picked) {
            foreach ($roleRows as $row) {
                $label = trim(strtolower(($row['n_role'] ?: $row['c_role']) ?? ''));
                if ($label === '') continue;

                if (self::roleLooksLikeAdminGrc($label)) {
                    $picked = ['type' => self::ROLE_ADMIN_GRC, 'id' => $row['i_id_role']];
                    break;
                }
            }
        }
        if (! $picked) {
            foreach ($roleRows as $row) {
                $label = trim(strtolower(($row['n_role'] ?: $row['c_role']) ?? ''));
                if ($label === '') continue;

                if (self::roleLooksLikeKadiv($label)) {
                    $picked = ['type' => self::ROLE_KADIV, 'id' => $row['i_id_role']];
                    break;
                }
            }
        }
        if (! $picked) {
            foreach ($roleRows as $row) {
                $label = trim(strtolower(($row['n_role'] ?: $row['c_role']) ?? ''));
                if ($label === '') continue;

                if (self::roleLooksLikeRiskOfficer($label)) {
                    $picked = ['type' => self::ROLE_RISK_OFFICER, 'id' => $row['i_id_role']];
                    break;
                }
            }
        }
        if (! $picked) {
            foreach ($roleRows as $row) {
                $label = trim(strtolower(($row['n_role'] ?: $row['c_role']) ?? ''));
                if ($label === '') continue;

                if (self::roleLooksLikeRsaEntry($label)) {
                    $picked = ['type' => self::ROLE_RSA_ENTRY, 'id' => $row['i_id_role']];
                    break;
                }
            }
        }

        if ($picked) {
            $roleType = $picked['type'];
            $roleId   = $picked['id'];
        }

        $orgPrefix = '';
        try {
            $orgPrefix = self::resolveUserOrgPrefix($userId, (string) ($user?->nik ?? ''));
        } catch (\Throwable) {
            $orgPrefix = '';
        }

        return self::$cachedContext = [
            'user'          => $user,
            'user_id'       => $userId,
            'is_superadmin' => (bool) $isSuperadmin,
            'role_type'     => $roleType,
            'role_id'       => $roleId,
            'org_prefix'    => $orgPrefix,
        ];
    }

    public static function isSuperAdmin(): bool
    {
        return (bool) (self::context()['is_superadmin'] ?? false);
    }

    public static function currentUserId(): int
    {
        return (int) (self::context()['user_id'] ?? 0);
    }

    public static function currentUserOrgPrefix(): string
    {
        return (string) (self::context()['org_prefix'] ?? '');
    }

    public static function canAccessRiskRegisterMenu(): bool
    {
        $ctx = self::context();
        if (($ctx['is_superadmin'] ?? false) === true) {
            return true;
        }

        return in_array(($ctx['role_type'] ?? self::ROLE_UNKNOWN), [
            self::ROLE_RSA_ENTRY,
            self::ROLE_RISK_OFFICER,
        ], true);
    }

    public static function canCreateRiskRegister(): bool
    {
        $ctx = self::context();
        if (($ctx['is_superadmin'] ?? false) === true) {
            return true;
        }

        return ($ctx['role_type'] ?? self::ROLE_UNKNOWN) === self::ROLE_RSA_ENTRY;
    }

    public static function applyRiskRegisterScope(Builder $query): Builder
    {
        $ctx = self::context();

        if (($ctx['is_superadmin'] ?? false) === true) {
            return $query;
        }

        $roleType = (string) ($ctx['role_type'] ?? self::ROLE_UNKNOWN);
        $userId   = (int) ($ctx['user_id'] ?? 0);
        $org      = trim((string) ($ctx['org_prefix'] ?? ''));

        if ($roleType === self::ROLE_RSA_ENTRY) {
            return $query->where('i_entry', $userId);
        }

        if ($roleType === self::ROLE_RISK_OFFICER) {
            if ($org === '') {
                return $query->whereRaw('1=0');
            }

            return $query->where('c_org_owner', $org);
        }

        return $query->whereRaw('1=0');
    }

    public static function canViewRiskRegisterRecord($record): bool
    {
        $ctx = self::context();

        if (($ctx['is_superadmin'] ?? false) === true) {
            return true;
        }

        $roleType = (string) ($ctx['role_type'] ?? self::ROLE_UNKNOWN);
        $userId   = (int) ($ctx['user_id'] ?? 0);
        $org      = trim((string) ($ctx['org_prefix'] ?? ''));

        if ($roleType === self::ROLE_RSA_ENTRY) {
            return (int) ($record->i_entry ?? 0) === $userId;
        }

        if ($roleType === self::ROLE_RISK_OFFICER) {
            return $org !== '' && trim((string) ($record->c_org_owner ?? '')) === $org;
        }

        return false;
    }

    public static function canEditRiskRegisterRecord($record): bool
    {
        $ctx = self::context();

        if (($ctx['is_superadmin'] ?? false) === true) {
            return true;
        }

        $roleType = (string) ($ctx['role_type'] ?? self::ROLE_UNKNOWN);
        $userId   = (int) ($ctx['user_id'] ?? 0);
        $org      = trim((string) ($ctx['org_prefix'] ?? ''));
        $status   = (int) ($record->c_risk_status ?? 0);

        if ($roleType === self::ROLE_RSA_ENTRY) {
            if ((int) ($record->i_entry ?? 0) !== $userId) {
                return false;
            }

            return in_array($status, [
                self::STATUS_DRAFT,
                self::STATUS_APPROVED_STAGE_1,
            ], true);
        }

        if ($roleType === self::ROLE_RISK_OFFICER) {
            if ($org === '' || trim((string) ($record->c_org_owner ?? '')) !== $org) {
                return false;
            }

            return $status === self::STATUS_APPROVED_STAGE_1;
        }

        return false;
    }

    private static function resolveUserOrgPrefix(int $userId, string $nik): string
    {
        $nik = trim($nik);

        if ($userId <= 0 && $nik === '') {
            return '';
        }

        $svc = app(EmployeeCacheService::class);

        $row = null;
        if ($userId > 0) {
            $row = $svc->findById($userId);
        }

        if (! $row && $nik !== '') {
            foreach ($svc->data() as $r) {
                if (! is_array($r)) continue;
                if ((string) ($r['nik'] ?? '') === $nik) {
                    $row = $r;
                    break;
                }
            }
        }

        $org = is_array($row)
            ? trim((string) ($row['organisasi'] ?? $row['organization'] ?? $row['org'] ?? ''))
            : '';

        if ($org === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z]{2})/', $org, $m)) {
            return strtoupper($m[1]);
        }

        return strtoupper(substr($org, 0, 2));
    }

    private static function roleLooksLikeRsaEntry(string $label): bool
    {
        return str_contains($label, 'rsa') && str_contains($label, 'entry');
    }

    private static function roleLooksLikeRiskOfficer(string $label): bool
    {
        if (self::roleLooksLikeApprovalGrc($label) || self::roleLooksLikeAdminGrc($label)) {
            return false;
        }

        if (str_contains($label, 'risk officer')) {
            return true;
        }

        return str_contains($label, 'risk') && str_contains($label, 'officer');
    }

    private static function roleLooksLikeKadiv(string $label): bool
    {
        return str_contains($label, 'kadiv')
            || str_contains($label, 'kepala divisi')
            || str_contains($label, 'division head')
            || (str_contains($label, 'head') && str_contains($label, 'division'));
    }

    private static function roleLooksLikeAdminGrc(string $label): bool
    {
        return str_contains($label, 'admin') && str_contains($label, 'grc');
    }

    private static function roleLooksLikeApprovalGrc(string $label): bool
    {
        return (str_contains($label, 'approval') && str_contains($label, 'grc'))
            || (str_contains($label, 'approver') && str_contains($label, 'grc'));
    }
}