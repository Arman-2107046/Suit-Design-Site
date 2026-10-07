<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Builder;

/*
 * Case-insensitive matching for the admin's global search, including inside
 * JSON columns (an order's customer name lives in `shipping`). MySQL compares
 * JSON text byte for byte, and stores non-Latin names escaped, so a plain LIKE
 * on `shipping->name` would miss "alex" for "Alex" and any Bengali name.
 */
final class Search
{
    public static function term(string $search): string
    {
        return '%'.mb_strtolower(trim($search)).'%';
    }

    /** lower(<the JSON value as plain text>), written for whichever database is in use. */
    public static function json(Builder $query, string $column, string $key): string
    {
        $grammar = $query->getQuery()->getGrammar();
        $column = $grammar->wrap($column);
        $path = "'$.\"".str_replace(['"', "'"], '', $key)."\"'";

        return match ($query->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "lower(json_unquote(json_extract({$column}, {$path})))",
            'pgsql' => "lower({$column}->>'".str_replace("'", '', $key)."')",
            default => "lower(json_extract({$column}, {$path}))",
        };
    }

    /** Adds "this JSON value contains the term" as an OR to a where-group. */
    public static function orJsonLike(Builder $query, string $column, string $key, string $term): Builder
    {
        return $query->orWhereRaw(self::json($query, $column, $key).' like ?', [$term]);
    }
}
