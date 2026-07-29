<?php

namespace App\Support;

class ScoreFormatter
{
    /**
     * Formats a decimal score/penalty value for display, trimming
     * trailing zeros without rounding (5.00 -> "5", 7.50 -> "7.5").
     */
    public static function trim(string|int|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }
}
