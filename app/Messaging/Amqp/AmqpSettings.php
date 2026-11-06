<?php

declare(strict_types=1);

namespace App\Messaging\Amqp;

use App\Support\Config;

/**
 * Validated RabbitMQ settings (see config/rabbitmq.php).
 */
final readonly class AmqpSettings
{
    private const string SECTION = 'RabbitMQ';

    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        public string $password,
        public string $vhost,
        public float $connectionTimeout,
        public float $readWriteTimeout,
        public int $heartbeat,
        public float $publishTimeout,
        public string $exchange,
        public string $routingKey,
        public string $queue,
        public string $deadLetterExchange,
        public string $deadLetterQueue,
        public int $prefetch,
    ) {
    }

    /**
     * @param array<string, mixed> $config Raw config('rabbitmq') array
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            host: Config::string(self::SECTION, $config, 'host'),
            port: Config::positiveInt(self::SECTION, $config, 'port'),
            user: Config::string(self::SECTION, $config, 'user'),
            password: Config::string(self::SECTION, $config, 'password'),
            vhost: Config::string(self::SECTION, $config, 'vhost'),
            connectionTimeout: Config::positiveFloat(self::SECTION, $config, 'connection_timeout'),
            readWriteTimeout: Config::positiveFloat(self::SECTION, $config, 'read_write_timeout'),
            heartbeat: Config::positiveInt(self::SECTION, $config, 'heartbeat'),
            publishTimeout: Config::positiveFloat(self::SECTION, $config, 'publish_timeout'),
            exchange: Config::string(self::SECTION, $config, 'exchange'),
            routingKey: Config::string(self::SECTION, $config, 'routing_key'),
            queue: Config::string(self::SECTION, $config, 'queue'),
            deadLetterExchange: Config::string(self::SECTION, $config, 'dead_letter_exchange'),
            deadLetterQueue: Config::string(self::SECTION, $config, 'dead_letter_queue'),
            prefetch: Config::positiveInt(self::SECTION, $config, 'prefetch'),
        );
    }
}
