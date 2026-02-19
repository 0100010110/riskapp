<?php

namespace App\Filament\Resources\LossEvents\Schemas;

use App\Models\Tmlostevent;
use App\Models\Tmtaxonomy;
use App\Support\TaxonomyFormatter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LossEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 1,
            ])
            ->schema([
                Section::make('Loss Event')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('i_id_taxonomy')
                            ->label('Taksonomi Risiko (Tier 5)')
                            ->relationship(
                                name: 'taxonomy',
                                titleAttribute: 'n_taxonomy',
                                modifyQueryUsing: fn (Builder $query) => $query->where('c_taxonomy_level', 5),
                            )
                            ->getOptionLabelFromRecordUsing(function (Tmtaxonomy $record): string {
                                $code = TaxonomyFormatter::formatCode(
                                    (string) $record->c_taxonomy,
                                    (int) ($record->c_taxonomy_level ?? 0),
                                );

                                $name = trim((string) $record->n_taxonomy);
                                return $name !== '' ? "{$code} - {$name}" : $code;
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('d_lost_event')
                            ->label('Tanggal Kejadian')
                            ->required()
                            ->default(fn (?Tmlostevent $record) => $record?->d_lost_event ?? now()),

                        Textarea::make('e_lost_event')
                            ->label('Kejadian / Peristiwa')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        TextInput::make('v_lost_event')
                            ->label('Kerugian')
                            ->required()
                            ->numeric()
                            ->rule('integer')
                            ->minValue(0)
                            ->placeholder('Contoh: 1000')
                            ->columnSpanFull(),

                        Hidden::make('c_lostevent_status')
                            ->default(fn (?Tmlostevent $record) => (int) ($record?->c_lostevent_status ?? Tmlostevent::STATUS_DRAFT))
                            ->dehydrated(true),
                    ]),
            ]);
    }
}
