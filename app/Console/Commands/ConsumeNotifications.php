<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsAsWorker;
use App\Messaging\Amqp\AmqpChannelProvider;
use App\Messaging\Exceptions\InvalidMessage;
use App\Messaging\Handlers\EmailMessageHandler;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Long-running consumer (the "consumer" container): reads "send an e-mail" messages from RabbitMQ,
 * sends the e-mail through Laravel Mail and acknowledges the message.
 * A message that cannot be processed is rejected to the dead letter queue.
 */
final class ConsumeNotifications extends Command
{
    use RunsAsWorker;

    private const int RECONNECT_DELAY_MS = 5000;

    protected $signature = 'notifications:consume
        {--max-messages=0 : Stop after processing this many messages (0 - run until stopped)}';

    protected $description = 'Consume e-mail notifications from RabbitMQ and send them';

    private int $processed = 0;

    public function handle(AmqpChannelProvider $channels, EmailMessageHandler $handler, LoggerInterface $logger): int
    {
        $maxMessages = max(0, (int) $this->option('max-messages'));
        $settings = $channels->settings();

        $this->listenForStopSignals();

        while (! $this->shouldStop()) {
            try {
                $channel = $channels->channel();
                $channel->basic_qos(0, $settings->prefetch, false);
                $channel->basic_consume(
                    $settings->queue,
                    '',
                    false,
                    false,
                    false,
                    false,
                    function (AMQPMessage $message) use ($handler, $logger, $maxMessages): void {
                        $this->process($message, $handler, $logger);

                        if ($maxMessages > 0 && ++$this->processed >= $maxMessages) {
                            $this->stop();
                        }
                    },
                );

                $this->log(sprintf('Waiting for messages in "%s".', $settings->queue));

                while (! $this->shouldStop() && $channel->is_consuming()) {
                    try {
                        // A short timeout lets the loop notice a stop signal.
                        $channel->wait(null, false, 1.0);
                    } catch (AMQPTimeoutException) {
                    }
                }
            } catch (Throwable $exception) {
                $logger->error('Notification consumer lost the connection to RabbitMQ.', ['error' => $exception->getMessage()]);
                $this->log(sprintf('Connection problem: %s. Reconnecting in %d s.', $exception->getMessage(), self::RECONNECT_DELAY_MS / 1000));
                $channels->close();
                $this->pause(self::RECONNECT_DELAY_MS);
            }
        }

        // Unacknowledged prefetched messages go back to the queue when the channel closes.
        $channels->close();
        $this->log('Consumer stopped.');

        return self::SUCCESS;
    }

    private function process(AMQPMessage $message, EmailMessageHandler $handler, LoggerInterface $logger): void
    {
        $messageId = $message->has('message_id') ? (string) $message->get('message_id') : null;
        $context = ['message_id' => $messageId];

        try {
            $sent = $handler->handle($message->getBody(), $messageId);
        } catch (InvalidMessage $exception) {
            $message->reject(false);
            $logger->warning('Invalid notification message moved to the dead letter queue.', $context + ['error' => $exception->getMessage()]);
            $this->log(sprintf('Rejected invalid message %s: %s', $messageId ?? '(no id)', $exception->getMessage()));

            return;
        } catch (Throwable $exception) {
            $message->reject(false);
            $logger->error('Notification could not be sent; message moved to the dead letter queue.', $context + ['error' => $exception->getMessage()]);
            $this->log(sprintf('Failed to send %s: %s', $messageId ?? '(no id)', $exception->getMessage()));

            return;
        }

        $message->ack();

        if ($sent) {
            $logger->info('Notification e-mail sent.', $context);
            $this->log(sprintf('E-mail sent for message %s.', $messageId ?? '(no id)'));
        } else {
            $this->log(sprintf('Duplicate message %s skipped.', $messageId ?? '(no id)'));
        }
    }
}
