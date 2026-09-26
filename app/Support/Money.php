<?php

namespace App\Support;

use App\Enums\CourseType;

/**
 * Money formatting for the interface.
 *
 * Amounts are stored as integer minor units with an ISO currency code, so the
 * only place a display string is built is here. A view can never print
 * `49900` or a float such as `499.0`.
 *
 * The interface is English, the currency is the Philippine Peso, and the
 * product plan gives `₱499.00` as the display format.
 */
class Money
{
    /**
     * The peso sign, the only currency the interface can show.
     */
    private const SYMBOLS = [
        'PHP' => '₱',
    ];

    /**
     * Format an amount for display, such as `₱499.00`.
     *
     * A negative amount keeps its sign outside the symbol, which reads as
     * `−₱50.00` rather than `₱-50.00`.
     */
    public static function format(int|string|null $amountMinor, string $currency = 'PHP'): string
    {
        $minor = (int) $amountMinor;
        $currency = strtoupper($currency);

        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return $sign.self::symbol($currency).number_format($whole).'.'
            .str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format a course price, or the word `Free` when the course costs nothing.
     *
     * The catalog and the course pages show `Free` instead of `₱0.00`, so a
     * free course is never read as a zero peso charge.
     *
     * The type is required here on purpose. A stored amount such as a payment
     * record has no course type, and it must never fall through to `Free`. Use
     * `format()` for any amount that was actually charged.
     */
    public static function course(int|string|null $amountMinor, CourseType|string $type, string $currency = 'PHP'): string
    {
        $isFree = $type instanceof CourseType
            ? $type === CourseType::Free
            : strtolower((string) $type) === CourseType::Free->value;

        if ($isFree || (int) $amountMinor === 0) {
            return 'Free';
        }

        return self::format($amountMinor, $currency);
    }

    /**
     * The ISO code on its own, for a line that names the currency in words.
     */
    public static function code(int|string|null $amountMinor, string $currency = 'PHP'): string
    {
        return strtoupper($currency);
    }

    private static function symbol(string $currency): string
    {
        // An unknown currency keeps its code so an amount is never shown with
        // the wrong symbol.
        return self::SYMBOLS[$currency] ?? $currency.' ';
    }
}
