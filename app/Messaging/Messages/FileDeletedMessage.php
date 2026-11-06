<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Enums\DeletionReason;
use Carbon\CarbonImmutable;

/**
 * "Send an e-mail: a file was deleted" message. Its array form is the JSON contract
 * consumed from RabbitMQ (see docs/PLAN.md, "Message contract").
 */
final readonly class FileDeletedMessage
{
    public const string TYPE = 'file.deleted';
    public const int VERSION = 1;

    public function __construct(
        public string $recipient,
        public string $subject,
        public string $body,
        public string $fileId,
        public string $originalName,
        public string $extension,
        public string $mimeType,
        public int $size,
        public CarbonImmutable $uploadedAt,
        public CarbonImmutable $expiresAt,
        public CarbonImmutable $deletedAt,
        public DeletionReason $reason,
    ) {
    }

    /**
     * @return array{
     *     version: int,
     *     email: array{to: string, subject: string, body: string},
     *     payload: array<string, int|string>
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'email' => [
                'to' => $this->recipient,
                'subject' => $this->subject,
                'body' => $this->body,
            ],
            'payload' => [
                'file_id' => $this->fileId,
                'original_name' => $this->originalName,
                'extension' => $this->extension,
                'mime_type' => $this->mimeType,
                'size' => $this->size,
                'uploaded_at' => $this->uploadedAt->utc()->toIso8601String(),
                'expires_at' => $this->expiresAt->utc()->toIso8601String(),
                'deleted_at' => $this->deletedAt->utc()->toIso8601String(),
                'reason' => $this->reason->value,
            ],
        ];
    }
}
