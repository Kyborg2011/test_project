<?php

declare(strict_types=1);

namespace App\Services\Files;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Validated settings of uploaded files (see config/files.php).
 * Invalid configuration fails fast instead of silently breaking uploads or the purge.
 */
final readonly class FileSettings
{
    /**
     * @param list<string> $allowedExtensions
     * @param array<string, list<string>> $mimeTypes MIME types for each allowed extension
     */
    public function __construct(
        public string $disk,
        public string $directory,
        public int $maxSizeKb,
        public array $allowedExtensions,
        public array $mimeTypes,
        public int $ttlMinutes,
        public int $perPage,
        public int $purgeBatchSize,
    ) {
        self::assertNotEmpty('disk', $disk);
        self::assertNotEmpty('directory', $directory);
        self::assertPositive('max_size_kb', $maxSizeKb);
        self::assertPositive('ttl_minutes', $ttlMinutes);
        self::assertPositive('per_page', $perPage);
        self::assertPositive('purge_batch_size', $purgeBatchSize);

        if ($allowedExtensions === []) {
            throw new InvalidArgumentException('Files config: "allowed_extensions" must not be empty.');
        }

        foreach ($allowedExtensions as $extension) {
            if (($mimeTypes[$extension] ?? []) === []) {
                throw new InvalidArgumentException(
                    sprintf('Files config: no MIME types are defined for extension "%s".', $extension),
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $config Raw config('files') array
     */
    public static function fromConfig(array $config): self
    {
        $allowedExtensions = self::parseList($config['allowed_extensions'] ?? []);

        $mimeTypes = [];
        $configuredMimeTypes = is_array($config['mime_types'] ?? null) ? $config['mime_types'] : [];

        foreach ($allowedExtensions as $extension) {
            $mimeTypes[$extension] = self::parseList($configuredMimeTypes[$extension] ?? []);
        }

        return new self(
            disk: self::toString($config['disk'] ?? ''),
            directory: trim(self::toString($config['directory'] ?? ''), '/'),
            maxSizeKb: self::toInt('max_size_kb', $config['max_size_kb'] ?? null),
            allowedExtensions: $allowedExtensions,
            mimeTypes: $mimeTypes,
            ttlMinutes: self::toInt('ttl_minutes', $config['ttl_minutes'] ?? null),
            perPage: self::toInt('per_page', $config['per_page'] ?? null),
            purgeBatchSize: self::toInt('purge_batch_size', $config['purge_batch_size'] ?? null),
        );
    }

    public function maxSizeBytes(): int
    {
        return $this->maxSizeKb * 1024;
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->mimeTypes))));
    }

    public function isAllowed(string $extension, string $mimeType): bool
    {
        return in_array($mimeType, $this->mimeTypes[strtolower($extension)] ?? [], true);
    }

    public function expiresAt(CarbonImmutable $uploadedAt): CarbonImmutable
    {
        return $uploadedAt->addMinutes($this->ttlMinutes);
    }

    /**
     * Accepts "pdf, DOCX" or ['pdf', 'docx'] and returns a normalised unique list.
     *
     * @return list<string>
     */
    private static function parseList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', self::toString($value));

        $items = array_map(static fn (mixed $item): string => strtolower(trim(self::toString($item))), $items);

        return array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== '')));
    }

    private static function toString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function toInt(string $key, mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false) {
            throw new InvalidArgumentException(sprintf('Files config: "%s" must be an integer.', $key));
        }

        return $int;
    }

    private static function assertPositive(string $key, int $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('Files config: "%s" must be greater than zero.', $key));
        }
    }

    private static function assertNotEmpty(string $key, string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('Files config: "%s" must not be empty.', $key));
        }
    }
}
