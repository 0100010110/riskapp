<?php

namespace App\Filament\Resources\Taxonomies\Schemas;

use App\Models\Tmtaxonomy;
use App\Models\Trscale;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TaxonomyForm
{
    public static function levelName(int $level): string
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

    public static function levelOptions(): array
    {
        return collect([1, 2, 3, 4, 5])
            ->mapWithKeys(fn (int $lvl) => [
                $lvl => sprintf('%d - %s', $lvl, self::levelName($lvl)),
            ])
            ->all();
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

    private static function normalizeSegment(string $segment): string
    {
        $segment = trim($segment);

        if ($segment !== '' && ctype_digit($segment) && strlen($segment) === 1) {
            $segment = str_pad($segment, 2, '0', STR_PAD_LEFT);
        }

        return $segment;
    }

    private static function stripPrefixIfAny(string $code): string
    {
        $code = trim($code);

        if (preg_match('/^[A-Za-z]{2}(.*)$/', $code, $m)) {
            return trim((string) $m[1]);
        }

        return $code;
    }

    private static function displayCode(int $level, ?string $numericPath): string
    {
        $numericPath = self::stripPrefixIfAny((string) $numericPath);
        $numericPath = trim($numericPath);

        if ($numericPath === '') {
            return '';
        }

        return self::prefixByLevel($level) . $numericPath;
    }

    public static function scaleOptions(): array
    {
        return Trscale::query()
            ->orderBy('c_scale_type')
            ->orderBy('i_scale')
            ->get()
            ->mapWithKeys(function (Trscale $s) {
                $code = trim((string) ($s->v_scale ?? ''));
                if ($code === '') {
                    $code = (string) ((int) ($s->i_scale ?? 0));
                }

                $assumption = trim((string) ($s->n_scale_assumption ?? ''));
                if ($assumption === '') {
                    $assumption = '-';
                }

                return [(int) $s->i_id_scale => "{$code} | {$assumption}"];
            })
            ->all();
    }

    public static function parentOptionsForLevel(?int $level, ?int $ignoreId = null, ?int $allowParentId = null): array
    {
        $level = (int) ($level ?: 0);
        if ($level <= 1) {
            return [];
        }

        $parentLevel = $level - 1;

        $tbl = (new Tmtaxonomy())->getTable();

        $parents = Tmtaxonomy::query()
            ->select(['i_id_taxonomy', 'c_taxonomy', 'c_taxonomy_level', 'n_taxonomy'])
            ->where('c_taxonomy_level', $parentLevel)
            ->when($ignoreId, fn ($q) => $q->where('i_id_taxonomy', '!=', (int) $ignoreId))
            ->where(function ($q) use ($tbl, $allowParentId) {
                $q->whereNotExists(function ($sq) use ($tbl) {
                    $sq->selectRaw('1')
                        ->from($tbl . ' as ch')
                        ->whereColumn('ch.i_id_taxonomyparent', $tbl . '.i_id_taxonomy');
                });

                if ($allowParentId) {
                    $q->orWhere($tbl . '.i_id_taxonomy', (int) $allowParentId);
                }
            })
            ->orderBy('c_taxonomy')
            ->get();

        return $parents
            ->mapWithKeys(function (Tmtaxonomy $t) {
                $lvl = (int) ($t->c_taxonomy_level ?? 1);
                $codeDisplay = self::displayCode($lvl, (string) $t->c_taxonomy);

                $label = sprintf(
                    '%s - %s (L%d %s)',
                    $codeDisplay,
                    (string) $t->n_taxonomy,
                    $lvl,
                    self::levelName($lvl),
                );

                return [(int) $t->i_id_taxonomy => $label];
            })
            ->all();
    }

    private static function syncComputed(Set $set, Get $get): void
    {
        $level = (int) ($get('c_taxonomy_level') ?: 0);
        $parentId = $get('i_id_taxonomyparent');
        $segment = self::normalizeSegment((string) ($get('_taxonomy_segment') ?? ''));

        if ($level <= 0) {
            $set('c_taxonomy', '');
            $set('_taxonomy_code_display', '');
            $set('i_id_taxonomyparent', null);
            $set('i_id_scale', []);
            return;
        }

        if ($level !== 5) {
            $set('i_id_scale', []);
        }

        if ($level === 1) {
            $set('i_id_taxonomyparent', null);

            if ($segment === '') {
                $set('c_taxonomy', '');
                $set('_taxonomy_code_display', '');
                return;
            }

            $set('c_taxonomy', $segment);
            $set('_taxonomy_code_display', self::displayCode($level, $segment));
            return;
        }

        if (! $parentId || $segment === '') {
            $set('c_taxonomy', '');
            $set('_taxonomy_code_display', '');
            return;
        }

        $parent = Tmtaxonomy::query()
            ->select(['i_id_taxonomy', 'c_taxonomy', 'c_taxonomy_level'])
            ->find((int) $parentId);

        if (! $parent || ((int) $parent->c_taxonomy_level) !== ($level - 1)) {
            $set('i_id_taxonomyparent', null);
            $set('c_taxonomy', '');
            $set('_taxonomy_code_display', '');
            return;
        }

        $parentPath = self::stripPrefixIfAny((string) $parent->c_taxonomy);
        $path = $parentPath . $segment; 
        $set('c_taxonomy', $path);
        $set('_taxonomy_code_display', self::displayCode($level, $path));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Taksonomi Risiko (Tema/Kategori/Peristiwa/Sumber/Dasar Risiko)')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    Select::make('c_taxonomy_level')
                        ->label('Level')
                        ->required()
                        ->native(false)
                        ->options(self::levelOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                            $lvl = (int) ($state ?: 0);

                            $set('i_id_taxonomyparent', null);

                            if ($lvl !== 5) {
                                $set('i_id_scale', []);
                            }

                            self::syncComputed($set, $get);
                        })
                        ->helperText(fn (Get $get) => ($lvl = (int) ($get('c_taxonomy_level') ?: 0))
                            ? ('Tipe: ' . self::levelName($lvl))
                            : 'Pilih level 1-5 terlebih dahulu.'
                        )
                        ->columnSpan(2),

                    Select::make('i_id_taxonomyparent')
                        ->label('Parent')
                        ->required(fn (Get $get) => (int) ($get('c_taxonomy_level') ?: 0) > 1)
                        ->options(fn (Get $get, ?Tmtaxonomy $record) => self::parentOptionsForLevel(
                            level: (int) ($get('c_taxonomy_level') ?: 0),
                            ignoreId: $record?->i_id_taxonomy,
                            allowParentId: (int) ($get('i_id_taxonomyparent') ?: ($record?->i_id_taxonomyparent ?: 0)) ?: null,
                        ))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get) => (int) ($get('c_taxonomy_level') ?: 0) <= 1)
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::syncComputed($set, $get))
                        ->helperText(function (Get $get): string {
                            $lvl = (int) ($get('c_taxonomy_level') ?: 0);
                            if ($lvl <= 0) return 'Pilih level terlebih dahulu.';
                            if ($lvl <= 1) return 'Level 1 tidak memiliki parent.';
                            return 'Parent difilter: hanya level ' . ($lvl - 1) . ' yang belum punya child.';
                        })
                        ->columnSpan(2),

                    TextInput::make('_taxonomy_segment')
                        ->label('Kode Level Ini')
                        ->required()
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(function (Set $set, Get $get, ?Tmtaxonomy $record) {
                            if (! $record) return;

                            $fullPath = self::stripPrefixIfAny((string) $record->c_taxonomy);

                            $parent = $record->parent;
                            if ($parent) {
                                $parentPath = self::stripPrefixIfAny((string) $parent->c_taxonomy);
                                $segment = substr($fullPath, strlen($parentPath));
                            } else {
                                $segment = $fullPath;
                            }

                            $set('_taxonomy_segment', $segment);
                            $lvl = (int) ($record->c_taxonomy_level ?? 1);
                            $set('_taxonomy_code_display', self::displayCode($lvl, $fullPath));
                        })
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::syncComputed($set, $get))
                        ->helperText('Contoh: 05 (akan digabung ke kode parent).')
                        ->columnSpan(1),

                    Hidden::make('c_taxonomy')
                        ->required()
                        ->dehydrated(true)
                        ->unique(table: 'tmtaxonomy', column: 'c_taxonomy', ignoreRecord: true),

                    TextInput::make('_taxonomy_code_display')
                        ->label('Kode (Otomatis)')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(function (Get $get): string {
                            $lvl = (int) ($get('c_taxonomy_level') ?: 0);
                            if ($lvl <= 0) return 'Dibentuk otomatis.';
                            $prefix = self::prefixByLevel($lvl);
                            return "Tampilan: {$prefix}<kode>. Prefix tidak disimpan di database.";
                        })
                        ->columnSpan(1),

                    Select::make('i_id_scale')
                        ->label('Skala')
                        ->native(false)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => self::scaleOptions())
                        ->disabled(fn (Get $get) => (int) ($get('c_taxonomy_level') ?: 0) !== 5)
                        ->required(fn (Get $get) => (int) ($get('c_taxonomy_level') ?: 0) === 5)
                        ->dehydrated(false) 
                        ->helperText(fn (Get $get): string =>
                            ((int) ($get('c_taxonomy_level') ?: 0) !== 5)
                                ? 'Skala hanya dapat diisi untuk taksonomi level 5.'
                                : 'Pilih skala Dampak & Kemungkinan sesuai kebutuhan.'
                        )
                        ->columnSpan(2),


                    TextInput::make('n_taxonomy')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('e_taxonomy')
                        ->label('Deskripsi')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
