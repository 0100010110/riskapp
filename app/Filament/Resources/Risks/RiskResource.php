<?php

namespace App\Filament\Resources\Risks;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Filament\Resources\Risks\Pages;
use App\Filament\Resources\Risks\Schemas\RiskForm;
use App\Filament\Resources\Risks\Tables\RisksTable;
use App\Models\Tmrisk;
use App\Support\PermissionBitmask;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RiskResource extends BaseResource
{
    protected static ?string $model = Tmrisk::class;

    protected static ?string $menuCode = 'risk';

    protected static UnitEnum|string|null $navigationGroup = 'Risk';
    protected static ?string $navigationLabel = 'Risk Register';
    protected static ?string $modelLabel = 'Risk Register';
    protected static ?string $pluralModelLabel = 'Risk Register';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'i_risk';

    public static function canAccess(): bool
    {
        if (parent::canAccess()) {
            return true;
        }

        $from = '';
        try {
            $from = strtolower(trim((string) request()->query('from', '')));
        } catch (\Throwable) {
            $from = '';
        }

        if ($from !== 'approval') {
            return false;
        }

        $route = request()->route();
        if (! $route) {
            return false;
        }

        $routeName = '';
        try {
            $routeName = (string) $route->getName();
        } catch (\Throwable) {
            $routeName = '';
        }

        $routeUri = '';
        try {
            $routeUri = (string) $route->uri();
        } catch (\Throwable) {
            $routeUri = '';
        }

        $isViewRoute =
            ($routeName !== '' && str_ends_with($routeName, '.view'))
            || ($routeUri === 'risks/{record}');

        $isEditRoute =
            ($routeName !== '' && str_ends_with($routeName, '.edit'))
            || ($routeUri === 'risks/{record}/edit');

        if (! $isViewRoute && ! $isEditRoute) {
            return false;
        }

        if ($isEditRoute && ! static::perm()->can(static::getMenuIdentifiers(), PermissionBitmask::UPDATE)) {
            return false;
        }

        if (! RiskApprovalResource::canViewAny()) {
            return false;
        }

        $recordParam = $route->parameter('record');

        $riskId = 0;
        if (is_object($recordParam) && method_exists($recordParam, 'getKey')) {
            $riskId = (int) $recordParam->getKey();
        } else {
            $riskId = (int) $recordParam;
        }

        if ($riskId <= 0) {
            return false;
        }

        $q = Tmrisk::query()->whereKey($riskId);
        $q = RiskApprovalWorkflow::applyApprovalListScope($q);

        return $q->exists();
    }

    public static function canView(Model $record): bool
    {
        if (parent::canView($record)) {
            return true;
        }

        $from = '';
        try {
            $from = strtolower(trim((string) request()->query('from', '')));
        } catch (\Throwable) {
            $from = '';
        }

        if ($from !== 'approval') {
            return false;
        }

        if (! RiskApprovalResource::canViewAny()) {
            return false;
        }

        $riskId = (int) ($record?->getKey() ?? 0);
        if ($riskId <= 0) {
            return false;
        }

        $q = Tmrisk::query()->whereKey($riskId);
        $q = RiskApprovalWorkflow::applyApprovalListScope($q);

        return $q->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return RiskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RisksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRisks::route('/'),
            'create' => Pages\CreateRisk::route('/create'),
            'edit'   => Pages\EditRisk::route('/{record}/edit'),
            'view'   => Pages\ViewRisk::route('/{record}'),
        ];
    }
}
