<?php

namespace App\Filament\Resources\LossEventApprovals;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\LossEventApprovals\Pages;
use App\Filament\Resources\LossEventApprovals\Tables\LossEventApprovalsTable;
use App\Models\Tmlostevent;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LossEventApprovalResource extends BaseResource
{
    protected static ?string $model = Tmlostevent::class;

    protected static ?string $menuCode = 'loss_event_approval';

    protected static UnitEnum|string|null $navigationGroup = 'LED';
    protected static ?string $navigationLabel = 'Loss Event Approval';
    protected static ?string $modelLabel = 'Loss Event Approval';
    protected static ?string $pluralModelLabel = 'Loss Event Approval';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-check-badge';
    protected static ?int $navigationSort = 10;

    protected static function hasApprovePermission(): bool
    {
        if (RiskApprovalWorkflow::isRealSuperadmin()) {
            return true;
        }

        // 16 = Approve
        return static::perm()->can(static::getMenuIdentifiers(), 16);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::hasApprovePermission();
    }

    public static function canViewAny(): bool
    {
        return static::hasApprovePermission();
    }

    public static function canView(Model $record): bool
    {
        return static::hasApprovePermission();
    }

    public static function canCreate(): bool
    {
        return static::hasApprovePermission();
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasApprovePermission();
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasApprovePermission();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['taxonomy']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return LossEventApprovalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLossEventApprovals::route('/'),
        ];
    }
}
