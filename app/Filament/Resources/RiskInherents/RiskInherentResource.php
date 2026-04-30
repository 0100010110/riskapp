<?php

namespace App\Filament\Resources\RiskInherents;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskInherents\Pages;
use App\Filament\Resources\RiskInherents\Schemas\RiskInherentForm;
use App\Filament\Resources\RiskInherents\Tables\RiskInherentsTable;
use App\Models\Tmriskinherent;
use App\Models\Trrole;
use App\Models\Truserrole;
use App\Policies\SuperadminPolicy;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RiskInherentResource extends BaseResource
{
    protected static ?string $model = Tmriskinherent::class;

    protected static ?string $menuCode = 'risk_inherent';

    protected static UnitEnum|string|null $navigationGroup = 'Risk';
    protected static ?string $navigationLabel = 'Risk Profile';
    protected static ?string $modelLabel = 'Risk Profile';
    protected static ?string $pluralModelLabel = 'Risk Profile';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 6;

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
        $query = parent::getEloquentQuery();

        $ctx = RiskApprovalWorkflow::context();

        $roleType = (string) ($ctx['role_type'] ?? '');
        $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);
        $uid      = (int) ($ctx['user_id'] ?? 0);
        $org      = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));

        if ($isSuper || static::hasSuperadminLikeRole()) {
            return $query;
        }

        if (in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ], true)) {
            return $query;
        }

        if (in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER,
            RiskApprovalWorkflow::ROLE_TYPE_OFFICER,
        ], true)) {
            if ($org === '') {
                return $query->whereRaw('1=0');
            }

            return $query->whereHas('risk', fn (Builder $q) => $q->where('c_org_owner', $org));
        }

        if ($roleType === RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY) {
            if ($uid <= 0) {
                return $query->whereRaw('1=0');
            }

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
        return RiskInherentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiskInherentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRiskInherents::route('/'),
            'create' => Pages\CreateRiskInherent::route('/create'),
            'edit'   => Pages\EditRiskInherent::route('/{record}/edit'),
        ];
    }
}