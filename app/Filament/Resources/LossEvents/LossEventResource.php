<?php

namespace App\Filament\Resources\LossEvents;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\LossEvents\Pages;
use App\Filament\Resources\LossEvents\Schemas\LossEventForm;
use App\Filament\Resources\LossEvents\Tables\LossEventsTable;
use App\Models\Tmlostevent;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LossEventResource extends BaseResource
{
    protected static ?string $model = Tmlostevent::class;

    protected static ?string $menuCode = 'loss_event';

    protected static UnitEnum|string|null $navigationGroup = 'LED';
    protected static ?string $navigationLabel = 'Loss Event';
    protected static ?string $modelLabel = 'Loss Event';
    protected static ?string $pluralModelLabel = 'Loss Event';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 9;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['taxonomy']);
    }

    public static function form(Schema $schema): Schema
    {
        return LossEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LossEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLossEvents::route('/'),
            'create' => Pages\CreateLossEvent::route('/create'),
            'edit'   => Pages\EditLossEvent::route('/{record}/edit'),
        ];
    }
}
