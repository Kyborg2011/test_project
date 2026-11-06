<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging;

use App\Messaging\Amqp\AmqpSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmqpSettingsTest extends TestCase
{
    public function test_builds_from_env_like_values(): void
    {
        $settings = AmqpSettings::fromConfig($this->config());

        $this->assertSame('rabbitmq', $settings->host);
        $this->assertSame(5672, $settings->port);
        $this->assertSame('/', $settings->vhost);
        $this->assertSame(2.5, $settings->connectionTimeout);
        $this->assertSame(30, $settings->heartbeat);
        $this->assertSame('notifications', $settings->exchange);
        $this->assertSame('email.send', $settings->routingKey);
        $this->assertSame('notifications.email', $settings->queue);
        $this->assertSame('notifications.dlx', $settings->deadLetterExchange);
        $this->assertSame('notifications.email.dead', $settings->deadLetterQueue);
        $this->assertSame(10, $settings->prefetch);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'empty host' => [['host' => ' '], '"host" must not be empty'];
        yield 'text port' => [['port' => 'amqp'], '"port" must be a positive integer'];
        yield 'zero timeout' => [['publish_timeout' => '0'], '"publish_timeout" must be a positive number'];
        yield 'empty queue' => [['queue' => ''], '"queue" must not be empty'];
        yield 'zero prefetch' => [['prefetch' => '0'], '"prefetch" must be a positive integer'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidConfigProvider')]
    public function test_rejects_invalid_config(array $override, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RabbitMQ config: ' . $expectedMessage);

        AmqpSettings::fromConfig($this->config($override));
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function config(array $override = []): array
    {
        return array_replace([
            'host' => 'rabbitmq',
            'port' => '5672',
            'user' => 'app',
            'password' => 'secret',
            'vhost' => '/',
            'connection_timeout' => '2.5',
            'read_write_timeout' => '10',
            'heartbeat' => '30',
            'publish_timeout' => '5',
            'exchange' => 'notifications',
            'routing_key' => 'email.send',
            'queue' => 'notifications.email',
            'dead_letter_exchange' => 'notifications.dlx',
            'dead_letter_queue' => 'notifications.email.dead',
            'prefetch' => '10',
        ], $override);
    }
}
