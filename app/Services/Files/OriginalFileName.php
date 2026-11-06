<?php

declare(strict_types=1);

namespace App\Services\Files;

/**
 * Makes a client-supplied file name safe to store and show: no path parts,
 * no control characters, valid UTF-8, limited length, the extension preserved.
 */
final class OriginalFileName
{
    public const int MAX_LENGTH = 255;

    private const string FALLBACK_BASE_NAME = 'file';

    public static function sanitize(string $name, string $extension): string
    {
        $extension = strtolower($extension);

        $name = mb_scrub($name, 'UTF-8');
        // Browsers may send a full path ("C:\fakepath\a.pdf"); only the last segment is the name.
        $name = (string) preg_replace('~^.*[/\\\\]~u', '', $name);
        $name = (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        $suffix = '.' . $extension;
        $baseName = self::stripSuffix($name, $suffix);
        $baseName = trim($baseName, " .");

        if ($baseName === '') {
            $baseName = self::FALLBACK_BASE_NAME;
        }

        $baseName = mb_substr($baseName, 0, self::MAX_LENGTH - mb_strlen($suffix));

        return rtrim($baseName) . $suffix;
    }

    private static function stripSuffix(string $name, string $suffix): string
    {
        return str_ends_with(mb_strtolower($name), $suffix) ? mb_substr($name, 0, -mb_strlen($suffix)) : $name;
    }
}
