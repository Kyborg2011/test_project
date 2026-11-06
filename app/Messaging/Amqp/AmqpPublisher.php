<?php

declare(strict_types=1);

namespace App\Messaging\Amqp;

use App\Messaging\Contracts\MessagePublisher;
use App\Messaging\Exceptions\PublishFailed;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Publishes persistent JSON messages with publisher confirms: publish() returns only when the broker
 * has stored the message in a queue. Unroutable messages (mandatory flag) and nacks are errors.
 */
final class AmqpPublisher implements MessagePublisher
{
    private ?AMQPChannel $confirmedChannel = null;

    private bool $acked = false;

    private bool $nacked = false;

    private ?string $returnedReason = null;

    public function __construct(
        private readonly AmqpChannelProvider $channels,
        private readonly string $applicationName,
    ) {
    }

    public function publish(string $messageId, string $type, array $body): void
    {
        $settings = $this->channels->settings();

        try {
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            throw new PublishFailed('Unable to encode the message as JSON: ' . $exception->getMessage(), 0, $exception);
        }

        $message = new AMQPMessage($payload, [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId,
            'type' => $type,
            'timestamp' => time(),
            'app_id' => $this->applicationName,
        ]);

        try {
            $channel = $this->confirmingChannel();

            $this->acked = false;
            $this->nacked = false;
            $this->returnedReason = null;

            $channel->basic_publish($message, $settings->exchange, $settings->routingKey, true);
            $channel->wait_for_pending_acks_returns($settings->publishTimeout);
        } catch (Throwable $exception) {
            // The connection state is unknown now: reconnect on the next publish.
            $this->channels->close();
            $this->confirmedChannel = null;

            throw new PublishFailed('Unable to publish the message to RabbitMQ: ' . $exception->getMessage(), 0, $exception);
        }

        $this->assertConfirmed();
    }

    /**
     * The flags are set by the channel callbacks while waiting for the broker's answer.
     */
    private function assertConfirmed(): void
    {
        if ($this->returnedReason !== null) {
            throw new PublishFailed('RabbitMQ could not route the message to a queue: ' . $this->returnedReason);
        }

        if ($this->nacked || ! $this->acked) {
            throw new PublishFailed('RabbitMQ did not confirm the message.');
        }
    }

    /**
     * Confirm mode and its handlers are set once per channel; a new channel appears after a reconnect.
     */
    private function confirmingChannel(): AMQPChannel
    {
        $channel = $this->channels->channel();

        if ($channel === $this->confirmedChannel) {
            return $channel;
        }

        $channel->set_ack_handler(function (): void {
            $this->acked = true;
        });
        $channel->set_nack_handler(function (): void {
            $this->nacked = true;
        });
        $channel->set_return_listener(function (int $replyCode, string $replyText): void {
            $this->returnedReason = sprintf('%d %s', $replyCode, $replyText);
        });
        $channel->confirm_select();

        $this->confirmedChannel = $channel;

        return $channel;
    }
}
