<?php

namespace App\Filament\Resources\RiskMitigations;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskMitigations\Pages;
use App\Filament\Resources\RiskMitigations\Schemas\RiskMitigationForm;
use App\Filament\Resources\RiskMitigations\Tables\RiskMitigationsTable;
use App\Models\Tmriskmitigation;
use App\Models\Trrole;
use App\Models\Truserrole;
use App\Policies\SuperadminPolicy;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RiskMitigationResource extends BaseResource
{
    protected static ?string $model = Tmriskmitigation::class;

    protected static ?string $menuCode = 'risk_mitigation';

    protected static UnitEnum|string|null $navigationGroup = 'Risk';
    protected static ?string $navigationLabel = 'Risk Mitigation';
    protected static ?string $modelLabel = 'Risk Mitigation';
    protected static ?string $pluralModelLabel = 'Risk Mitigation';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 7;

    protected static ?bool $hasSuperadminLikeRoleCache = null;

    protected static function currentUser()
    {
        $user = null;

        try {
            $user = Filament::auth()->user();
        } catch (\Throwable) {
            $user = null;
        }

        return $user ?? auth()->user();
    }

    /**
     * @return array<int>
     */
    protected static function currentUserRoleIds(): array
    {
        $user = static::currentUser();
        $uid  = (int) ($user?->getAuthIdentifier() ?? 0);

        if ($uid <= 0) {
            return [];
        }

        $roleIds = Truserrole::query()
            ->where('i_id_user', $uid)
            ->pluck('i_id_role')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if (! empty($roleIds)) {
            return $roleIds;
        }

        $nikRaw = trim((string) ($user?->nik ?? ''));
        if ($nikRaw !== '' && ctype_digit($nikRaw)) {
            $nik = (int) $nikRaw;

            if ($nik > 0 && $nik !== $uid) {
                $roleIds = Truserrole::query()
                    ->where('i_id_user', $nik)
                    ->pluck('i_id_role')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($v) => $v > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return $roleIds;
    }

    protected static function hasSuperadminLikeRole(): bool
    {
        if (static::$hasSuperadminLikeRoleCache !== null) {
            return static::$hasSuperadminLikeRoleCache;
        }

        $user = static::currentUser();

        try {
            if (SuperadminPolicy::isSuperadmin($user)) {
                return static::$hasSuperadminLikeRoleCache = true;
            }
        } catch (\Throwable) {
        }

        $roleIds = static::currentUserRoleIds();

        if (empty($roleIds)) {
            return static::$hasSuperadminLikeRoleCache = false;
        }

        $exists = Trrole::query()
            ->whereIn('i_id_role', $roleIds)
            ->where('f_active', true)
            ->where(function (Builder $q) {
                $q->whereRaw("LOWER(COALESCE(c_role,'')) LIKE ?", ['%superadmin%'])
                    ->orWhereRaw("LOWER(COALESCE(n_role,'')) LIKE ?", ['%superadmin%'])
                    ->orWhereRaw("LOWER(COALESCE(c_role,'')) LIKE ?", ['%super admin%'])
                    ->orWhereRaw("LOWER(COALESCE(n_role,'')) LIKE ?", ['%super admin%']);
            })
            ->exists();

        return static::$hasSuperadminLikeRoleCache = $exists;
    }

    protected static function canManageByWorkflow(): bool
    {
        $ctx = RiskApprovalWorkflow::context();

        if ((bool) ($ctx['is_superadmin'] ?? false)) {
            return true;
        }

        if (static::hasSuperadminLikeRole()) {
            return true;
        }

        $roleType = (string) ($ctx['role_type'] ?? '');

        return in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
            RiskApprovalWorkflow::ROLE_TYPE_OFFICER,
        ], true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['riskInherent.risk']);

        $ctx      = RiskApprovalWorkflow::context();
        $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);
        $roleType = (string) ($ctx['role_type'] ?? '');
        $org      = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));
        $uid      = (int) ($ctx['user_id'] ?? 0);

        if ($isSuper || static::hasSuperadminLikeRole()) {
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

    public static function canCreate(): bool
    {
        if (! parent::canCreate()) {
            return false;
        }

        return static::canManageByWorkflow();
    }

    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        return static::canManageByWorkflow();
    }

    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        return static::canManageByWorkflow();
    }

    public static function form(Schema $schema): Schema
    {
        return RiskMitigationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiskMitigationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRiskMitigations::route('/'),
            'create' => Pages\CreateRiskMitigation::route('/create'),
            'edit'   => Pages\EditRiskMitigation::route('/{record}/edit'),
        ];
    }
}