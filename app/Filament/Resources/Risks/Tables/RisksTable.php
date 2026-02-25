<?php

namespace App\Filament\Resources\Risks\Tables;

use App\Filament\Resources\Risks\RiskResource;
use App\Models\Tmrisk;
use App\Support\TaxonomyFormatter;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use App\Support\RiskWorkflow;

class RisksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->toolbarActions([
                Actions\Action::make('print_risk_register')
                    ->label(fn ($livewire) => ($livewire->printMode ?? false) ? 'Print Selected' : 'Print')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('warning')
                    ->action(fn ($livewire) => $livewire->handlePrintToolbarAction()),
            ])

            ->selectable(fn ($livewire) => (bool) ($livewire->printMode ?? false))

            ->currentSelectionLivewireProperty(fn ($livewire) =>
                (bool) ($livewire->printMode ?? false)
                    ? 'printSelectedRecordIds'
                    : null
            )

            ->deselectAllRecordsWhenFiltered(false)
            ->selectCurrentPageOnly(false)

            ->modifyQueryUsing(fn (Builder $query) => $query->with(['taxonomy']))

            ->filters([
                Filter::make('need_action')
                    ->label('Need Action')
                    ->query(function (Builder $query) {
                        return $query
                            ->where('c_risk_status', 4)
                            ->where(function (Builder $q) {
                                $q->whereNull('d_update')
                                    ->orWhereRaw(
                                        "d_update <= (
                                            select max(a.d_entry)
                                            from tmriskapprove a
                                            where a.i_id_risk = tmrisk.i_id_risk
                                        )"
                                    );
                            });
                    }),
            ])

            ->groups([
                Group::make('c_risk_year')
                    ->label('Tahun')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn (Tmrisk $record): string =>
                        trim((string) ($record->c_risk_year ?? ''))
                    )
                    ->getTitleFromRecordUsing(fn (Tmrisk $record): string =>
                        trim((string) ($record->c_risk_year ?? '')) ?: '-'
                    ),
            ])
            ->defaultGroup('c_risk_year')

            ->columns([
                Tables\Columns\TextColumn::make('taxonomy.c_taxonomy')
                    ->label('Taxonomy Code')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state, Tmrisk $record) => TaxonomyFormatter::formatCode(
                        $state,
                        (int) ($record->taxonomy?->c_taxonomy_level ?? null)
                    )),

                Tables\Columns\TextColumn::make('taxonomy.n_taxonomy')
                    ->label('Taxonomy Name')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('c_risk_year')
                    ->label('Year')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('c_org_owner')
                    ->label('Divisi')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('e_risk_event')
                    ->label('Risk Event')
                    ->sortable()
                    ->searchable()
                    ->limit(80)
                    ->wrap(),

                Tables\Columns\IconColumn::make('f_risk_primary')
                    ->label('Primary')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('c_risk_status')
                    ->label('Status')
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->extraAttributes([
                        'class' => 'whitespace-normal break-words max-w-xs',
                    ])
                    ->formatStateUsing(fn ($state, Tmrisk $record) => $record->statusLabelWithActor()),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Created At')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '';
                        }

                        try {
                            $dt = $state instanceof Carbon ? $state : Carbon::parse($state);
                            return $dt->format('M d, Y') . '<br>' . $dt->format('H:i:s');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    })
                    ->html(),
            ])

            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('use_for_current_year')
                        ->label('Gunakan untuk Tahun ini')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Gunakan untuk Tahun ini')
                        ->modalDescription(function (Tmrisk $record): string {
                            $thisYear = (int) now()->format('Y');
                            $oldYear  = trim((string) ($record->c_risk_year ?? ''));
                            return "Akan membuat Risk Register baru (copy dari tahun {$oldYear}) untuk tahun {$thisYear}.\n"
                                . "Nomor Risiko akan dikosongkan, dan Status kembali menjadi Draft.";
                        })
                        ->visible(function (Tmrisk $record): bool {
                            $thisYear = (int) now()->format('Y');
                            $year = is_numeric($record->c_risk_year) ? (int) $record->c_risk_year : 0;
                            return $year > 0 && $year < $thisYear && RiskResource::canCreate();
                        })
                        ->action(function (Tmrisk $record, $livewire) {
                            $thisYear = (string) now()->format('Y');

                            
                            $new = $record->replicate();

                            foreach (['i_entry', 'd_entry', 'i_update', 'd_update'] as $col) {
                                unset($new->{$col});
                            }

                            $pk = $record->getKeyName();
                            if ($pk) {
                                unset($new->{$pk});
                            }

                            $new->c_risk_year   = $thisYear;
                            $new->i_risk        = 'null'; // (biarkan sesuai behavior lama)
                            $new->c_risk_status = 0;

                            try {
                                $new->save();
                            } catch (QueryException $e) {
                                // fallback jika i_risk 'null' memicu constraint tertentu
                                $new->i_risk = 'TEMP';
                                $new->save();
                            }

                            Notification::make()
                                ->success()
                                ->title('Risk Register dibuat')
                                ->body("Berhasil membuat copy untuk tahun {$thisYear}. Silakan lengkapi data.")
                                ->send();

                            return redirect()->to(RiskResource::getUrl('edit', ['record' => $new]));
                        }),

                    Actions\EditAction::make()
                        ->visible(fn ($record) => RiskResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => RiskResource::canDelete($record)),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn ($record) =>
                        RiskResource::canEdit($record) ||
                        RiskResource::canDelete($record) ||
                        (RiskResource::canCreate() && is_numeric($record->c_risk_year) && (int) $record->c_risk_year < (int) now()->format('Y'))
                    ),
            ]);
    }
}