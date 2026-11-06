<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Messaging\Contracts\MessagePublisher;
use App\Messaging\Exceptions\PublishFailed;

/**
 * In-memory publisher: records published messages and can simulate an unavailable broker.
 */
final class FakeMessagePublisher implements MessagePublisher
{
    /** @var list<array{message_id: string, type: string, body: array<string, mixed>}> */
    public array $published = [];

    /** Number of successful publishes before the broker "goes down"; null - never fails. */
    private ?int $failAfter = null;

    public function failAfter(int $successfulPublishes): void
    {
        $this->failAfter = $successfulPublishes;
    }

    public function recover(): void
    {
        $this->failAfter = null;
    }

    public function publish(string $messageId, string $type, array $body): void
    {
        if ($this->failAfter !== null && count($this->published) >= $this->failAfter) {
            throw new PublishFailed('Connection refused');
        }

        $this->published[] = ['message_id' => $messageId, 'type' => $type, 'body' => $body];
    }

    /**
     * @return list<string>
     */
    public function publishedIds(): array
    {
        return array_column($this->published, 'message_id');
    }
}
