<?php

namespace App\Support;

/**
 * Money is handled as integer cents everywhere it is added up or discounted:
 * 0.1 + 0.2 !== 0.3 in floating point, and a cent lost per line becomes a
 * wrong order total. Decimal strings are what we hand back to the database.
 */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Percentage of an amount, rounded to the nearest cent. */
    public static function percentOf(int $cents, string|int|float $percent): int
    {
        return (int) round($cents * ((float) $percent / 100));
    }
}
