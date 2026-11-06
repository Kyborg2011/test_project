<?php

declare(strict_types=1);

namespace App\Messaging\Contracts;

use App\Messaging\Exceptions\PublishFailed;

interface MessagePublisher
{
    /**
     * Publishes the message and returns only after the broker has accepted it.
     *
     * @param array<string, mixed> $body
     *
     * @throws PublishFailed
     */
    public function publish(string $messageId, string $type, array $body): void;
}
