<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Taxonomies\Pages\CreateTaxonomy;
use App\Filament\Resources\Taxonomies\Pages\EditTaxonomy;
use App\Filament\Resources\Taxonomies\Pages\ListTaxonomies;
use App\Filament\Resources\Taxonomies\Schemas\TaxonomyForm;
use App\Filament\Resources\Taxonomies\Tables\TaxonomiesTable;
use App\Models\Tmtaxonomy;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TaxonomyResource extends BaseResource
{
    protected static ?string $model = Tmtaxonomy::class;

   
    protected static ?string $menuCode = 'taxonomy';

    protected static UnitEnum|string|null $navigationGroup = 'Risk';
    protected static ?string $navigationLabel = 'Risk Taxonomy';
    protected static ?string $pluralModelLabel = 'Risk Taxonomy';
    protected static ?string $modelLabel = 'Risk Taxonomy';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'n_taxonomy';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent.parent.parent.parent']);
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxonomiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTaxonomies::route('/'),
            'create' => CreateTaxonomy::route('/create'),
            'edit'   => EditTaxonomy::route('/{record}/edit'),
        ];
    }
}
