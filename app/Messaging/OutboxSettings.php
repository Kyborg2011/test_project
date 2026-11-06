<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Support\Config;
use InvalidArgumentException;

/**
 * Validated outbox relay settings (see config/outbox.php).
 */
final readonly class OutboxSettings
{
    private const string SECTION = 'Outbox';

    public function __construct(
        public int $batchSize,
        public int $pollIntervalMs,
        public int $retryBaseSeconds,
        public int $retryMaxSeconds,
    ) {
        if ($retryMaxSeconds < $retryBaseSeconds) {
            throw new InvalidArgumentException('Outbox config: "retry_max_seconds" must not be less than "retry_base_seconds".');
        }
    }

    /**
     * @param array<string, mixed> $config Raw config('outbox') array
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            batchSize: Config::positiveInt(self::SECTION, $config, 'batch_size'),
            pollIntervalMs: Config::positiveInt(self::SECTION, $config, 'poll_interval_ms'),
            retryBaseSeconds: Config::positiveInt(self::SECTION, $config, 'retry_base_seconds'),
            retryMaxSeconds: Config::positiveInt(self::SECTION, $config, 'retry_max_seconds'),
        );
    }

    /**
     * Delay before the next publish attempt after $attempts failed ones: base, 2*base, 4*base, ... up to max.
     */
    public function retryDelaySeconds(int $attempts): int
    {
        $exponent = min(max($attempts, 1) - 1, 30);

        return min($this->retryBaseSeconds * (2 ** $exponent), $this->retryMaxSeconds);
    }
}
