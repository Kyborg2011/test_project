<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging;

use App\Messaging\OutboxSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboxSettingsTest extends TestCase
{
    public function test_builds_from_env_like_values(): void
    {
        $settings = OutboxSettings::fromConfig($this->config());

        $this->assertSame(50, $settings->batchSize);
        $this->assertSame(1000, $settings->pollIntervalMs);
        $this->assertSame(5, $settings->retryBaseSeconds);
        $this->assertSame(300, $settings->retryMaxSeconds);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function retryDelayProvider(): iterable
    {
        yield 'first failure' => [1, 5];
        yield 'second failure' => [2, 10];
        yield 'third failure' => [3, 20];
        yield 'sixth failure' => [6, 160];
        yield 'capped by max' => [7, 300];
        yield 'huge attempt number does not overflow' => [1000, 300];
        yield 'zero is treated as first' => [0, 5];
    }

    #[DataProvider('retryDelayProvider')]
    public function test_retry_delay_grows_exponentially_up_to_max(int $attempts, int $expectedSeconds): void
    {
        $this->assertSame($expectedSeconds, OutboxSettings::fromConfig($this->config())->retryDelaySeconds($attempts));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'zero batch' => [['batch_size' => '0'], '"batch_size" must be a positive integer'];
        yield 'text interval' => [['poll_interval_ms' => 'often'], '"poll_interval_ms" must be a positive integer'];
        yield 'missing base' => [['retry_base_seconds' => null], '"retry_base_seconds" must be a positive integer'];
        yield 'max below base' => [['retry_base_seconds' => '60', 'retry_max_seconds' => '10'], '"retry_max_seconds" must not be less than'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidConfigProvider')]
    public function test_rejects_invalid_config(array $override, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        OutboxSettings::fromConfig($this->config($override));
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function config(array $override = []): array
    {
        return array_replace([
            'batch_size' => '50',
            'poll_interval_ms' => '1000',
            'retry_base_seconds' => '5',
            'retry_max_seconds' => '300',
        ], $override);
    }
}
