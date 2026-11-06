<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

final class Duration
{
    /**
     * Compact time left until a moment, two largest units: "1 d 2 h", "3 h 5 min", "12 min", "< 1 min".
     * public/js/files.js formats the live countdown the same way.
     */
    public static function remaining(DateTimeInterface $now, DateTimeInterface $until): string
    {
        $seconds = $until->getTimestamp() - $now->getTimestamp();

        if ($seconds <= 0) {
            return 'expired';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes === 0) {
            return '< 1 min';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $minutes %= 60;

        $parts = array_filter([
            $days > 0 ? $days . ' d' : null,
            $hours > 0 ? $hours . ' h' : null,
            $minutes > 0 ? $minutes . ' min' : null,
        ]);

        return implode(' ', array_slice($parts, 0, 2));
    }
}
