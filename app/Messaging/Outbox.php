<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Transactional outbox writer. Call it inside the same database transaction as the business change:
 * the message is then stored if and only if the change is committed, and a separate relay publishes it.
 */
final class Outbox
{
    /**
     * @param array<string, mixed> $data Message-specific part of the body
     */
    public function record(string $type, array $data): OutboxMessage
    {
        $messageId = Str::uuid7()->toString();
        $now = CarbonImmutable::now();

        $message = new OutboxMessage([
            'type' => $type,
            // The stored body is published as is; the envelope fields come first.
            'payload' => [
                'message_id' => $messageId,
                'type' => $type,
                'occurred_at' => $now->utc()->toIso8601String(),
                ...$data,
            ],
            'available_at' => $now,
        ]);
        $message->message_id = $messageId;
        $message->save();

        return $message;
    }
}
