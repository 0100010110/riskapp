<?php

namespace App\Filament\Resources\Taxonomies\Tables;

use App\Filament\Resources\Taxonomies\TaxonomyResource;
use App\Models\Tmtaxonomy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;

class TaxonomiesTable
{
    private static function levelName(int $level): string
    {
        return match ($level) {
            1 => 'Tema',
            2 => 'Kategori',
            3 => 'Peristiwa',
            4 => 'Sumber',
            5 => 'Dasar',
            default => 'Level ' . $level,
        };
    }

    private static function prefixByLevel(int $level): string
    {
        return match ($level) {
            1 => 'TR',
            2 => 'KR',
            3 => 'PR',
            4 => 'SR',
            5 => 'DR',
            default => 'LV',
        };
    }

    private static function stripPrefixIfAny(?string $code): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]{2}(.*)$/', $code, $m)) {
            return trim((string) $m[1]);
        }

        return $code;
    }

    private static function displayCodeForRecord(Tmtaxonomy $record): string
    {
        $lvl = (int) ($record->c_taxonomy_level ?? 1);
        $path = self::stripPrefixIfAny((string) ($record->c_taxonomy ?? ''));

        if ($path === '') {
            return '';
        }

        return self::prefixByLevel($lvl) . $path;
    }

    private static function displayCodeForParent(?Tmtaxonomy $parent): string
    {
        if (! $parent) return '';
        $lvl = (int) ($parent->c_taxonomy_level ?? 1);
        $path = self::stripPrefixIfAny((string) ($parent->c_taxonomy ?? ''));
        if ($path === '') return '';
        return self::prefixByLevel($lvl) . $path;
    }

    private static function rootCodeFromTaxonomyCode(?string $code): ?string
    {
        $code = self::stripPrefixIfAny((string) $code);
        $code = trim($code);
        if ($code === '') return null;

        return substr($code, 0, 2);
    }

    public static function configure(Table $table): Table
    {
        $rootExpr = "left(regexp_replace(c_taxonomy, '^[A-Za-z]{2}', ''), 2)";

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('c_taxonomy')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, Tmtaxonomy $record) {
                        return self::displayCodeForRecord($record) ?: '-';
                    }),

                Tables\Columns\TextColumn::make('n_taxonomy')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state, Tmtaxonomy $record) {
                        $level = (int) ($record->c_taxonomy_level ?? 1);
                        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(0, $level - 1));
                        return new HtmlString($indent . e($state));
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('c_taxonomy_level')
                    ->label('Level')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => (int) $state . ' - ' . self::levelName((int) $state)),

                Tables\Columns\TextColumn::make('parent_id')
                    ->label('Parent')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(function (Tmtaxonomy $record) {
                        $record->loadMissing('parent');
                        if (! $record->parent) return 'Root';

                        $code = self::displayCodeForParent($record->parent);
                        $name = (string) ($record->parent->n_taxonomy ?? '');
                        return trim($code) !== '' ? "{$code} - {$name}" : $name;
                    }),

                Tables\Columns\TextColumn::make('d_entry')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])

            ->groups([
                Group::make('root_code')
                    ->label('Taksonomi')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)

                    ->getKeyFromRecordUsing(function (Tmtaxonomy $record) {
                        $root = $record->rootTaxonomy();
                        $code = $root?->c_taxonomy ?: $record->c_taxonomy;
                        return self::rootCodeFromTaxonomyCode($code) ?? '-';
                    })

                    ->getTitleFromRecordUsing(function (Tmtaxonomy $record) {
                        $root = $record->rootTaxonomy();

                        if ($root) {
                            $rootNumeric = self::stripPrefixIfAny((string) $root->c_taxonomy);
                            $rootDisplay = self::prefixByLevel(1) . $rootNumeric;

                            return sprintf('%s - %s', $rootDisplay, (string) $root->n_taxonomy);
                        }

                        $key = self::rootCodeFromTaxonomyCode($record->c_taxonomy) ?? 'Root';
                        return self::prefixByLevel(1) . $key;
                    })

                    ->groupQueryUsing(function (QueryBuilder $query) use ($rootExpr) {
                        return $query->groupByRaw($rootExpr);
                    })

                    ->orderQueryUsing(function (EloquentBuilder $query, string $direction) use ($rootExpr) {
                        return $query
                            ->orderByRaw($rootExpr . ' ' . $direction)
                            ->orderBy('c_taxonomy', 'asc')
                            ->orderBy('i_id_taxonomy', 'asc');
                    })

                    ->scopeQueryByKeyUsing(function (EloquentBuilder $query, ?string $key) use ($rootExpr) {
                        if (blank($key)) {
                            return $query;
                        }
                        return $query->whereRaw($rootExpr . ' = ?', [$key]);
                    }),
            ])
            ->defaultGroup('root_code')
            ->groupingSettingsHidden()

            ->headerActions([])
            ->emptyStateActions([])

            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()
                        ->visible(fn ($record) => TaxonomyResource::canEdit($record)),

                    Actions\DeleteAction::make()
                        ->visible(fn ($record) => TaxonomyResource::canDelete($record))
                        ->action(function ($record) {
                            try {
                                $record->delete();
                            } catch (QueryException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak bisa menghapus')
                                    ->body('Taksonomi masih dipakai (punya child atau direferensikan tabel lain).')
                                    ->send();
                            }
                        }),
                ])
                ->visible(fn ($record) => TaxonomyResource::canEdit($record) || TaxonomyResource::canDelete($record)),
            ])

            ->defaultSort('c_taxonomy', 'asc');
    }
}
