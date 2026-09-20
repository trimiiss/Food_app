<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The handful of places where SQL dialects disagree.
 *
 * The app runs on MySQL or SQLite locally and on PostgreSQL when deployed
 * (see render.yaml), so anything the three spell differently is written once
 * here instead of being discovered in production.
 */
final class Sql
{
    /**
     * `LIKE` is case-insensitive on MySQL and SQLite but case-sensitive on
     * PostgreSQL, where `ILIKE` is the equivalent.
     */
    public static function likeOperator(): string
    {
        return self::driver() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Truncates a timestamp column to its date, for grouping by day.
     * The column name is never user input — always a literal in our queries.
     */
    public static function dateExpression(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "CAST({$column} AS DATE)",
            default => "DATE({$column})",
        };
    }

    private static function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
