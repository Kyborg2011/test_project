<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Models\StoredFile;
use App\Support\Bytes;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use LogicException;

/**
 * Builds the "file deleted" e-mail message for a file that has just been marked as deleted.
 */
final readonly class FileDeletedMessageFactory
{
    public function __construct(
        private ViewFactory $views,
        private string $recipient,
        private string $applicationName,
    ) {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                sprintf('Notifications config: "%s" is not a valid e-mail address (NOTIFICATION_EMAIL).', $recipient),
            );
        }
    }

    public function make(StoredFile $file): FileDeletedMessage
    {
        if ($file->deleted_at === null || $file->deletion_reason === null) {
            throw new LogicException('The message can be built only for a deleted file.');
        }

        $body = trim($this->views->make('messages.file-deleted', [
            'file' => $file,
            'size' => Bytes::format($file->size),
            'applicationName' => $this->applicationName,
        ])->render()) . "\n";

        return new FileDeletedMessage(
            recipient: $this->recipient,
            subject: sprintf('File "%s" has been deleted', $file->original_name),
            body: $body,
            fileId: $file->uuid,
            originalName: $file->original_name,
            extension: $file->extension,
            mimeType: $file->mime_type,
            size: $file->size,
            uploadedAt: $file->created_at,
            expiresAt: $file->expires_at,
            deletedAt: $file->deleted_at,
            reason: $file->deletion_reason,
        );
    }
}
