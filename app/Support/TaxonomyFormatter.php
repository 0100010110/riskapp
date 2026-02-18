<?php

namespace App\Support;

use App\Models\Tmtaxonomy;

class TaxonomyFormatter
{
  
    public static function prefixForLevel(?int $level): string
    {
        return match ((int) $level) {
            1 => 'TR',
            2 => 'KR',
            3 => 'PR',
            4 => 'SR',
            5 => 'DR',
            default => 'TM',
        };
    }

    public static function formatCode(?string $code, ?int $level = null): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        if (preg_match('/^(TM|TR|KR|PR|SR|DR)/i', $code)) {
            return strtoupper($code);
        }

        $prefix = self::prefixForLevel($level);

        return $prefix . $code;
    }

    public static function code(?Tmtaxonomy $taxonomy): string
    {
        if (! $taxonomy) {
            return '';
        }

        $level = (int) ($taxonomy->c_taxonomy_level ?? 0);
        $code  = (string) ($taxonomy->c_taxonomy ?? '');

        return self::formatCode($code, $level);
    }

    public static function label(?Tmtaxonomy $taxonomy): string
    {
        if (! $taxonomy) {
            return '';
        }

        $code = self::code($taxonomy);
        $name = trim((string) ($taxonomy->n_taxonomy ?? ''));

        if ($code !== '' && $name !== '') {
            return "{$code} - {$name}";
        }

        return $code !== '' ? $code : $name;
    }
}
