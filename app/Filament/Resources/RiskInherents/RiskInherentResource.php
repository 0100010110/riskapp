<?php

namespace App\Filament\Resources\RiskInherents;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskInherents\Pages;
use App\Filament\Resources\RiskInherents\Schemas\RiskInherentForm;
use App\Filament\Resources\RiskInherents\Tables\RiskInherentsTable;
use App\Models\Tmriskinherent;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $ctx = RiskApprovalWorkflow::context();

        $roleType = (string) ($ctx['role_type'] ?? '');
        $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);
        $uid      = (int) ($ctx['user_id'] ?? 0);
        $org      = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));

        if ($isSuper) {
            return $query;
        }

        if (in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC,
            RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC,
        ], true)) {
            return $query;
        }

        if ($roleType === RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER) {
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

        $ctx = RiskApprovalWorkflow::context();
        if ((bool) ($ctx['is_superadmin'] ?? false)) {
            return true;
        }

        return (string) ($ctx['role_type'] ?? '') === RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER;
    }

    public static function canEdit($record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        $ctx = RiskApprovalWorkflow::context();
        if ((bool) ($ctx['is_superadmin'] ?? false)) {
            return true;
        }

        return (string) ($ctx['role_type'] ?? '') === RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER;
    }

    public static function canDelete($record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        $ctx = RiskApprovalWorkflow::context();
        if ((bool) ($ctx['is_superadmin'] ?? false)) {
            return true;
        }

        return (string) ($ctx['role_type'] ?? '') === RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER;
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
