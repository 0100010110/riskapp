<?php

namespace App\Filament\Resources\ScaleMaps\Tables;

use App\Filament\Resources\ScaleMaps\ScaleMapResource;
use App\Models\Trscalemap;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;

class ScaleMapsTable
{
    protected static function colorName(string $rgb): string
    {
        return match ($rgb) {
            '63,81,181'   => 'Blue',
            '76,175,80'   => 'Green',
            '255,235,59'  => 'Yellow',
            '255,152,0'   => 'Orange',
            '244,67,54'   => 'Red',
            '0,188,212'   => 'Cyan',
            '156,39,176'  => 'Purple',
            '233,30,99'   => 'Magenta',
            '121,85,72'   => 'Brown',
            '96,125,139'  => 'Blue Grey',
            default       => 'Custom',
        };
    }

    protected static function colorBadge(string $rgb): string
    {
        return (string) new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:8px;">
                <span style="
                    width:14px;
                    height:14px;
                    border-radius:4px;
                    border:1px solid rgba(107,114,128,0.8);
                    background:rgb(' . e($rgb) . ');
                "></span>
                <span>' . e(self::colorName($rgb)) . '</span>
            </span>'
        );
    }

    protected static function recordValue(Trscalemap $record, string $attribute, mixed $fallback = ''): mixed
    {
        $value = $record->getAttribute($attribute);

        return $value !== null && $value !== ''
            ? $value
            : $fallback;
    }

    protected static function groupKey(Trscalemap $record): string
    {
        $codeA = trim((string) self::recordValue(
            $record,
            'group_code_a',
            $record->scaleDetailA?->scale?->v_scale
        ));

        $codeB = trim((string) self::recordValue(
            $record,
            'group_code_b',
            $record->scaleDetailB?->scale?->v_scale
        ));

        return $codeA . '|' . $codeB;
    }

    protected static function groupTitle(Trscalemap $record): string
    {
        $codeA = trim((string) self::recordValue(
            $record,
            'group_code_a',
            $record->scaleDetailA?->scale?->v_scale
        ));

        $codeB = trim((string) self::recordValue(
            $record,
            'group_code_b',
            $record->scaleDetailB?->scale?->v_scale
        ));

        $nameA = trim((string) self::recordValue(
            $record,
            'group_name_a',
            $record->scaleDetailA?->scale?->n_scale_assumption
        ));

        $nameB = trim((string) self::recordValue(
            $record,
            'group_name_b',
            $record->scaleDetailB?->scale?->n_scale_assumption
        ));

        $title = trim(implode(' dan ', array_values(array_filter([$nameA, $nameB]))));
        $codes = trim(implode(' | ', array_values(array_filter([$codeA, $codeB]))));

        if ($title !== '' && $codes !== '') {
            return $title . ' (' . $codes . ')';
        }

        if ($title !== '') {
            return $title;
        }

        return $codes !== '' ? $codes : '-';
    }

    public static function configure(Table $table): Table
    {
        $mapTable = (new Trscalemap())->getTable();

        $exprCodeA = "COALESCE(sa.v_scale, '')";
        $exprCodeB = "COALESCE(sb.v_scale, '')";

        return $table
            ->modifyQueryUsing(function (EloquentBuilder $query) use ($mapTable): EloquentBuilder {
                return $query
                    ->leftJoin('trscaledetail as sda', 'sda.i_id_scaledetail', '=', $mapTable . '.i_id_scale_a')
                    ->leftJoin('trscale as sa', 'sa.i_id_scale', '=', 'sda.i_id_scale')
                    ->leftJoin('trscaledetail as sdb', 'sdb.i_id_scaledetail', '=', $mapTable . '.i_id_scale_b')
                    ->leftJoin('trscale as sb', 'sb.i_id_scale', '=', 'sdb.i_id_scale')
                    ->select($mapTable . '.*')
                    ->selectRaw("COALESCE(sa.v_scale, '') as group_code_a")
                    ->selectRaw("COALESCE(sa.n_scale_assumption, '') as group_name_a")
                    ->selectRaw("COALESCE(sb.v_scale, '') as group_code_b")
                    ->selectRaw("COALESCE(sb.n_scale_assumption, '') as group_name_b")
                    ->with([
                        'scaleDetailA.scale',
                        'scaleDetailB.scale',
                    ])
                    ->orderByRaw("COALESCE(sa.v_scale, '') asc")
                    ->orderByRaw("COALESCE(sb.v_scale, '') asc")
                    ->orderBy($mapTable . '.i_map', 'asc')
                    ->orderBy($mapTable . '.i_id_scalemap', 'asc');
            })
            ->groups([
                Group::make('map_pair_group')
                    ->label('Group')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn (Trscalemap $record): string => self::groupKey($record))
                    ->getTitleFromRecordUsing(fn (Trscalemap $record): string => self::groupTitle($record))
                    ->orderQueryUsing(function (EloquentBuilder $query, string $direction) use ($exprCodeA, $exprCodeB, $mapTable): EloquentBuilder {
                        return $query
                            ->orderByRaw($exprCodeA . ' ' . $direction)
                            ->orderByRaw($exprCodeB . ' ' . $direction)
                            ->orderBy($mapTable . '.i_map', 'asc')
                            ->orderBy($mapTable . '.i_id_scalemap', 'asc');
                    })
                    ->scopeQueryByKeyUsing(function (EloquentBuilder $query, ?string $key) use ($exprCodeA, $exprCodeB): EloquentBuilder {
                        if (blank($key)) {
                            return $query;
                        }

                        [$codeA, $codeB] = array_pad(explode('|', (string) $key, 2), 2, '');

                        return $query
                            ->whereRaw($exprCodeA . ' = ?', [$codeA])
                            ->whereRaw($exprCodeB . ' = ?', [$codeB]);
                    }),
            ])
            ->defaultGroup('map_pair_group')
            ->columns([
                Tables\Columns\TextColumn::make('scaleDetailA.scale.v_scale')
                    ->label('Kode Dampak')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scaleDetailA.i_detail_score')
                    ->label('Dampak')
                    ->sortable(),

                Tables\Columns\TextColumn::make('scaleDetailB.scale.v_scale')
                    ->label('Kode Kemungkinan')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scaleDetailB.i_detail_score')
                    ->label('Kemungkinan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('i_map')
                    ->label('Nilai Map')
                    ->sortable(),

                Tables\Columns\TextColumn::make('c_map')
                    ->label('Color')
                    ->html()
                    ->formatStateUsing(fn ($state) =>
                        self::colorBadge((string) $state)
                    ),

                Tables\Columns\TextColumn::make('n_map')
                    ->label('Penjelasan')
                    ->limit(40)
                    ->wrap()
                    ->searchable(),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => ScaleMapResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => ScaleMapResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Data masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn ($record) =>
                        ScaleMapResource::canEdit($record) || ScaleMapResource::canDelete($record)
                    ),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => ScaleMapResource::canDeleteAny())
                        ->action(function ($records) {
                            try {
                                $records->each->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Sebagian data masih dipakai oleh tabel lain (foreign key).')
                                    ->send();
                            }
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->visible(fn () => ScaleMapResource::canDeleteAny()),
            ])
            ->defaultSort('i_map', 'asc');
    }
}