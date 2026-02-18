<?php

namespace App\Filament\Resources\ScaleMaps;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ScaleMaps\Pages\CreateScaleMap;
use App\Filament\Resources\ScaleMaps\Pages\EditScaleMap;
use App\Filament\Resources\ScaleMaps\Pages\ListScaleMaps;
use App\Filament\Resources\ScaleMaps\Schemas\ScaleMapForm;
use App\Filament\Resources\ScaleMaps\Tables\ScaleMapsTable;
use App\Models\Trscalemap;
use App\Models\Trscaledetail;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ScaleMapResource extends BaseResource
{
    protected static ?string $model = Trscalemap::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'Heat Map';
    protected static ?string $pluralModelLabel = 'Heat Map';
    protected static ?string $modelLabel = 'Heat Map';
    protected static UnitEnum|string|null $navigationGroup = 'Skala';
    protected static ?int $navigationSort = 20;

    protected static ?string $menuCode = 'scalemap';

    protected static ?string $recordTitleAttribute = 'n_map';

    
    public static function computeMapValue(?int $scaleDetailAId, ?int $scaleDetailBId): ?int
    {
        if (! $scaleDetailAId || ! $scaleDetailBId) {
            return null;
        }

        $aScore = Trscaledetail::query()->find($scaleDetailAId)?->i_detail_score;
        $bScore = Trscaledetail::query()->find($scaleDetailBId)?->i_detail_score;

        if ($aScore === null || $bScore === null) {
            return null;
        }

        return ((int) $aScore) * ((int) $bScore);
    }

    public static function form(Schema $schema): Schema
    {
        return ScaleMapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScaleMapsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScaleMaps::route('/'),
            'create' => CreateScaleMap::route('/create'),
            'edit' => EditScaleMap::route('/{record}/edit'),
        ];
    }
}
