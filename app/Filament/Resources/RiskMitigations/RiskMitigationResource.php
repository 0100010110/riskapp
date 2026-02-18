<?php

namespace App\Filament\Resources\RiskMitigations;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskMitigations\Pages;
use App\Filament\Resources\RiskMitigations\Schemas\RiskMitigationForm;
use App\Filament\Resources\RiskMitigations\Tables\RiskMitigationsTable;
use App\Models\Tmriskmitigation;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function form(Schema $schema): Schema
    {
        return RiskMitigationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiskMitigationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery()
        ->with(['riskInherent.risk']);

    $ctx      = \App\Support\RiskApprovalWorkflow::context();
    $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);
    $roleType = (string) ($ctx['role_type'] ?? '');
    $org      = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));
    $uid      = (int) ($ctx['user_id'] ?? 0);

    if ($isSuper) {
        return $query;
    }

    if (in_array($roleType, [
        \App\Support\RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC ?? 'admin_grc',
        \App\Support\RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC ?? 'approval_grc',
        \App\Support\RiskApprovalWorkflow::ROLE_TYPE_GRC ?? 'grc',
    ], true)) {
        return $query;
    }

    if (in_array($roleType, [
        \App\Support\RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER ?? 'risk_officer',
        \App\Support\RiskApprovalWorkflow::ROLE_TYPE_OFFICER ?? 'officer',
        \App\Support\RiskApprovalWorkflow::ROLE_TYPE_KADIV ?? 'kadiv',
    ], true)) {
        if ($org === '') {
            return $query->whereRaw('1=0');
        }

        return $query->whereHas('riskInherent.risk', fn (Builder $q) => $q->where('c_org_owner', $org));
    }

    if (($roleType === (\App\Support\RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY ?? 'rsa_entry')) && $uid > 0) {
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

        $roleType = (string) ($ctx['role_type'] ?? '');

        return in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER ?? 'risk_officer',
            RiskApprovalWorkflow::ROLE_TYPE_OFFICER ?? 'officer', // legacy
        ], true);
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

        $roleType = (string) ($ctx['role_type'] ?? '');

        return in_array($roleType, [
            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER ?? 'risk_officer',
            RiskApprovalWorkflow::ROLE_TYPE_OFFICER ?? 'officer',
        ], true);
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
