<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Messaging\Contracts\MessagePublisher;
use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Publishes due outbox messages to the broker, oldest first.
 *
 * Rows are locked with FOR UPDATE SKIP LOCKED, so several relays never publish the same message.
 * A message is marked published only after the broker confirmed it. If the process dies between
 * the confirm and the commit, the message is published again: delivery is at-least-once and the
 * consumer drops duplicates by message_id.
 */
final readonly class OutboxRelay
{
    public function __construct(
        private DatabaseManager $db,
        private MessagePublisher $publisher,
        private OutboxSettings $settings,
        private LoggerInterface $logger,
    ) {
    }

    public function relayBatch(): RelayResult
    {
        return $this->db->transaction(function (): RelayResult {
            $messages = OutboxMessage::query()
                ->pending()
                ->limit($this->settings->batchSize)
                ->lock('for update skip locked')
                ->get();

            $published = 0;

            foreach ($messages as $message) {
                try {
                    $this->publisher->publish($message->message_id, $message->type, $message->payload);
                } catch (Throwable $exception) {
                    $this->postpone($message, $exception);

                    // The broker is most likely unavailable: stop the batch instead of failing every message.
                    return new RelayResult($published, failed: true);
                }

                $message->markPublished(CarbonImmutable::now());
                $published++;
            }

            return new RelayResult($published, failed: false);
        });
    }

    private function postpone(OutboxMessage $message, Throwable $exception): void
    {
        $delay = $this->settings->retryDelaySeconds($message->attempts + 1);

        $message->markFailed($exception->getMessage(), CarbonImmutable::now()->addSeconds($delay));

        $this->logger->warning('Outbox message was not published; it will be retried.', [
            'message_id' => $message->message_id,
            'attempts' => $message->attempts,
            'retry_in_seconds' => $delay,
            'error' => $exception->getMessage(),
        ]);
    }
}
