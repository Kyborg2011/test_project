<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging;

use App\Enums\DeletionReason;
use App\Messaging\Messages\FileDeletedMessage;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class FileDeletedMessageTest extends TestCase
{
    public function test_serialises_to_the_message_contract(): void
    {
        $message = new FileDeletedMessage(
            recipient: 'admin@example.com',
            subject: 'File "a.pdf" has been deleted',
            body: "Hello\n",
            fileId: '01a1b24b-5363-737a-be60-d100aeea234c',
            originalName: 'a.pdf',
            extension: 'pdf',
            mimeType: 'application/pdf',
            size: 1234,
            uploadedAt: CarbonImmutable::parse('2026-11-06 12:00:00', 'UTC'),
            expiresAt: CarbonImmutable::parse('2026-11-07 12:00:00', 'UTC'),
            // Non-UTC input is normalised to UTC in the message.
            deletedAt: CarbonImmutable::parse('2026-11-07 14:00:05', 'Europe/Kyiv'),
            reason: DeletionReason::Expired,
        );

        $this->assertSame([
            'version' => 1,
            'email' => [
                'to' => 'admin@example.com',
                'subject' => 'File "a.pdf" has been deleted',
                'body' => "Hello\n",
            ],
            'payload' => [
                'file_id' => '01a1b24b-5363-737a-be60-d100aeea234c',
                'original_name' => 'a.pdf',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 1234,
                'uploaded_at' => '2026-11-06T12:00:00+00:00',
                'expires_at' => '2026-11-07T12:00:00+00:00',
                'deleted_at' => '2026-11-07T12:00:05+00:00',
                'reason' => 'expired',
            ],
        ], $message->toArray());
    }

    public function test_type_is_stable(): void
    {
        $this->assertSame('file.deleted', FileDeletedMessage::TYPE);
    }
}
