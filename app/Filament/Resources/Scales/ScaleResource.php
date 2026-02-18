<?php

namespace App\Filament\Resources\Scales;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Scales\Pages\CreateScale;
use App\Filament\Resources\Scales\Pages\EditScale;
use App\Filament\Resources\Scales\Pages\ListScales;
use App\Filament\Resources\Scales\Schemas\ScaleForm;
use App\Filament\Resources\Scales\Tables\ScalesTable;
use App\Models\Trscale;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ScaleResource extends BaseResource
{
    protected static ?string $model = Trscale::class;

    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Skala Dampak / Kemungkinan';
    protected static ?string $pluralModelLabel = 'Skala Dampak / Kemungkinan';
    protected static ?string $modelLabel = 'Skala Dampak / Kemungkinan';
    protected static UnitEnum|string|null $navigationGroup = 'Skala';
    protected static ?int $navigationSort = 10;

    protected static ?string $menuCode = 'scale';

    protected static ?string $recordTitleAttribute = 'v_scale';

    public static function makeScaleCode(?string $scaleType, mixed $financeFlag, mixed $scaleNumber): string
    {
        if (! filled($scaleType) || ! filled($scaleNumber)) {
            return '';
        }

        $prefix = ((string) $scaleType === '1') ? 'DP' : 'SK';
        $finance = filter_var($financeFlag, FILTER_VALIDATE_BOOLEAN) ? '01' : '00';

        $num = (int) $scaleNumber;
        $numPadded = str_pad((string) $num, 4, '0', STR_PAD_LEFT);

        return $prefix . $finance . $numPadded;
    }

    public static function form(Schema $schema): Schema
    {
        return ScaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScales::route('/'),
            'create' => CreateScale::route('/create'),
            'edit' => EditScale::route('/{record}/edit'),
        ];
    }
}
