<?php

declare(strict_types=1);

namespace App\Support;

final class Bytes
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    /**
     * Human-readable size with binary multiples: 1536 -> "1.5 KB", 10485760 -> "10 MB".
     */
    public static function format(int $bytes): string
    {
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count(self::UNITS) - 1) {
            $value /= 1024;
            $unit++;
        }

        $formatted = $unit === 0 ? (string) (int) $value : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return $formatted . ' ' . self::UNITS[$unit];
    }
}
