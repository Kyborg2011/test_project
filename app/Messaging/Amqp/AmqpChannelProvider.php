<?php

declare(strict_types=1);

namespace App\Messaging\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Lazily opens one connection + channel per process, (re)declares the topology on every (re)connect
 * and reconnects after close(). Declarations are idempotent, so publisher and consumer can both run them.
 */
final class AmqpChannelProvider
{
    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(private readonly AmqpSettings $settings)
    {
    }

    public function settings(): AmqpSettings
    {
        return $this->settings;
    }

    public function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open() && $this->connection?->isConnected() === true) {
            return $this->channel;
        }

        $this->close();

        $this->connection = AMQPConnectionFactory::create($this->connectionConfig());
        $channel = $this->connection->channel();
        $this->declareTopology($channel);

        return $this->channel = $channel;
    }

    /**
     * Drops the connection; the next channel() call reconnects. Safe to call on a broken connection.
     */
    public function close(): void
    {
        try {
            $this->channel?->close();
        } catch (Throwable) {
            // The channel is already unusable.
        }

        try {
            $this->connection?->close();
        } catch (Throwable) {
            // The connection is already unusable.
        }

        $this->channel = null;
        $this->connection = null;
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $s = $this->settings;

        // Dead letters: rejected messages end up here for inspection.
        $channel->exchange_declare($s->deadLetterExchange, 'fanout', false, true, false);
        $channel->queue_declare($s->deadLetterQueue, false, true, false, false);
        $channel->queue_bind($s->deadLetterQueue, $s->deadLetterExchange);

        $channel->exchange_declare($s->exchange, 'direct', false, true, false);
        $channel->queue_declare(
            $s->queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => $s->deadLetterExchange]),
        );
        $channel->queue_bind($s->queue, $s->exchange, $s->routingKey);
    }

    private function connectionConfig(): AMQPConnectionConfig
    {
        $config = new AMQPConnectionConfig();
        $config->setHost($this->settings->host);
        $config->setPort($this->settings->port);
        $config->setUser($this->settings->user);
        $config->setPassword($this->settings->password);
        $config->setVhost($this->settings->vhost);
        $config->setConnectionTimeout($this->settings->connectionTimeout);
        $config->setReadTimeout($this->settings->readWriteTimeout);
        $config->setWriteTimeout($this->settings->readWriteTimeout);
        $config->setHeartbeat($this->settings->heartbeat);
        $config->setIsLazy(false);

        return $config;
    }
}
