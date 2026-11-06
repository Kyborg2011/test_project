<?php

declare(strict_types=1);

/*
 * Outbox relay (outbox:relay): publishes messages from the outbox_messages table to RabbitMQ.
 */
return [
    // Messages published per pass.
    'batch_size' => env('OUTBOX_BATCH_SIZE', 50),

    // Pause between passes when there is nothing to publish (or the broker is unavailable), milliseconds.
    'poll_interval_ms' => env('OUTBOX_POLL_INTERVAL_MS', 1000),

    // Delay before retrying a failed message: base * 2^(attempt - 1), capped by max. Seconds.
    'retry_base_seconds' => env('OUTBOX_RETRY_BASE_SECONDS', 5),
    'retry_max_seconds' => env('OUTBOX_RETRY_MAX_SECONDS', 300),
];
