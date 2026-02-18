<?php

namespace App\Filament\Resources\RiskRealizations;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RiskRealizations\Pages;
use App\Filament\Resources\RiskRealizations\Schemas\RiskRealizationForm;
use App\Filament\Resources\RiskRealizations\Tables\RiskRealizationsTable;
use App\Models\Tmriskrealization;
use App\Support\RiskApprovalWorkflow;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RiskRealizationResource extends BaseResource
{
    protected static ?string $model = Tmriskrealization::class;

    protected static ?string $menuCode = 'risk_realization';

    protected static UnitEnum|string|null $navigationGroup = 'Risk';
    protected static ?string $navigationLabel = 'Risk Realization';
    protected static ?string $modelLabel = 'Risk Realization';
    protected static ?string $pluralModelLabel = 'Risk Realization';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?int $navigationSort = 8;

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();

        $q->with([
            'riskInherent.risk.taxonomy',
            'riskInherent.scaleMapInherent',
            'riskInherent.scaleMapResidual',
            'scaleMap',
        ]);

        $q->whereHas('riskInherent.risk', function (Builder $rq) {
            RiskApprovalWorkflow::applyRiskRegisterScope($rq);
        });

        return $q;
    }

    public static function form(Schema $schema): Schema
    {
        return RiskRealizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiskRealizationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRiskRealizations::route('/'),
            'create' => Pages\CreateRiskRealization::route('/create'),
            'edit'   => Pages\EditRiskRealization::route('/{record}/edit'),
        ];
    }
}
