<?php

namespace App\Filament\Resources\LossEvents\Tables;

use App\Models\Tmlostevent;
use App\Support\TaxonomyFormatter;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LossEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Tmlostevent::query()->with(['taxonomy']))
            ->defaultSort('d_entry', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('taxonomy.c_taxonomy')
                    ->label('Taxonomy Code')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state, Tmlostevent $record): string {
                        return TaxonomyFormatter::formatCode(
                            (string) $state,
                            (int) ($record->taxonomy?->c_taxonomy_level ?? 0),
                        );
                    }),

                Tables\Columns\TextColumn::make('d_lost_event')
                    ->label('Event Date')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '';
                        try {
                            $dt = $state instanceof Carbon ? $state : Carbon::parse($state);
                            return $dt->format('Y-m-d');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    }),

                Tables\Columns\TextColumn::make('v_lost_event')
                    ->label('Kerugian')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state): string {
                        $v = is_numeric($state) ? (int) $state : null;
                        return $v === null ? '' : number_format($v, 0, '.', ',');
                    }),

                Tables\Columns\TextColumn::make('e_lost_event')
                    ->label('Loss Event')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('c_lostevent_status')
                    ->label('Status')
                    ->sortable()
                    ->formatStateUsing(function ($state, Tmlostevent $record): string {
                        return method_exists($record, 'statusLabelWithActor')
                            ? (string) $record->statusLabelWithActor()
                            : (string) ((int) ($state ?? 0));
                    }),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Submitted At')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '';
                        try {
                            $dt = $state instanceof Carbon ? $state : Carbon::parse($state);
                            return $dt->format('Y-m-d') . '<br>' . $dt->format('H:i:s');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    })
                    ->html(),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make(),
                ])
                    ->icon(Heroicon::OutlinedBars3)
                    ->label('')
                    ->tooltip('Actions'),
            ]);
    }
}
