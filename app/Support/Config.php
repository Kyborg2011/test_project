<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Typed reading of raw config arrays filled from .env (where every value arrives as a string).
 */
final class Config
{
    /**
     * @param array<string, mixed> $config
     */
    public static function string(string $section, array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        $string = is_scalar($value) ? trim((string) $value) : '';

        if ($string === '') {
            throw new InvalidArgumentException(sprintf('%s config: "%s" must not be empty.', $section, $key));
        }

        return $string;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function positiveInt(string $section, array $config, string $key): int
    {
        $int = filter_var($config[$key] ?? null, FILTER_VALIDATE_INT);

        if ($int === false || $int <= 0) {
            throw new InvalidArgumentException(sprintf('%s config: "%s" must be a positive integer.', $section, $key));
        }

        return $int;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function positiveFloat(string $section, array $config, string $key): float
    {
        $float = filter_var($config[$key] ?? null, FILTER_VALIDATE_FLOAT);

        if ($float === false || $float <= 0) {
            throw new InvalidArgumentException(sprintf('%s config: "%s" must be a positive number.', $section, $key));
        }

        return $float;
    }
}
