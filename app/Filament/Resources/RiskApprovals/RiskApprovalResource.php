<?php

namespace App\Filament\Resources\RiskApprovals;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskApprovals\Pages;
use App\Filament\Resources\RiskApprovals\Schemas\RiskApprovalForm;
use App\Filament\Resources\RiskApprovals\Tables\RiskApprovalsTable;
use App\Models\Tmriskapprove;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RiskApprovalResource extends BaseResource
{
    protected static ?string $model = Tmriskapprove::class;

    
    protected static ?string $menuCode = 'riskapprove';

    protected static ?string $pluralModelLabel = 'Risk Approvals';
    protected static ?string $modelLabel = 'Risk Approvals';
    
    protected static UnitEnum|string|null $navigationGroup = 'Risk';
    protected static ?string $navigationLabel = 'Risk Approval';
    
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-check-badge';

    
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return RiskApprovalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiskApprovalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRiskApprovals::route('/'),
            'create' => Pages\CreateRiskApproval::route('/create'),
            'edit'   => Pages\EditRiskApproval::route('/{record}/edit'),
        ];
    }
}
