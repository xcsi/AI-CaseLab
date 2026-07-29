<?php

namespace App\Support;

use App\Enums\CaseDifficulty;

class Badge
{
    /**
     * Bootstrap badge class for a case's difficulty — shared across every
     * screen that shows a difficulty chip (Inbox, Assigned Incidents,
     * Incident Briefing).
     */
    public static function difficulty(?CaseDifficulty $difficulty): string
    {
        return match ($difficulty) {
            CaseDifficulty::Easy => 'text-bg-success',
            CaseDifficulty::Medium => 'text-bg-warning',
            CaseDifficulty::Hard => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }

    /**
     * Bootstrap badge class for a score percentage — shared across every
     * screen that shows a score chip (Inbox, Assigned Incidents,
     * Performance Review), matching the design system's <50 / 50-75 / >75
     * color bands.
     */
    public static function score(float $percent): string
    {
        return match (true) {
            $percent < 50 => 'text-bg-danger',
            $percent < 75 => 'text-bg-warning',
            default => 'text-bg-success',
        };
    }
}
