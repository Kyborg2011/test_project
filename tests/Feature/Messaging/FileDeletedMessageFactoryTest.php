<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\DeletionReason;
use App\Messaging\Messages\FileDeletedMessageFactory;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class FileDeletedMessageFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['notifications.recipient' => 'ops@example.com', 'app.name' => 'File Storage']);
        $this->app->forgetInstance(FileDeletedMessageFactory::class);
    }

    public function test_builds_email_for_manually_deleted_file(): void
    {
        $message = $this->factory()->make($this->deletedFile(DeletionReason::Manual));

        $this->assertSame('ops@example.com', $message->recipient);
        $this->assertSame('File "Отчёт <final>.pdf" has been deleted', $message->subject);
        $this->assertSame(
            <<<'TEXT'
            Hello,

            The file "Отчёт <final>.pdf" has been deleted (deleted manually).

            File:        Отчёт <final>.pdf
            Type:        PDF
            Size:        1.5 KB
            Uploaded at: 2026-11-06 12:00:00 UTC
            Deleted at:  2026-11-06 15:30:00 UTC

            This is an automatic message from File Storage.

            TEXT,
            $message->body,
        );
    }

    public function test_builds_email_for_expired_file(): void
    {
        $file = $this->deletedFile(DeletionReason::Expired);

        $message = $this->factory()->make($file);

        $this->assertStringContainsString('has been deleted (expired).', $message->body);
        $this->assertSame(DeletionReason::Expired, $message->reason);
        $this->assertSame($file->uuid, $message->fileId);
        $this->assertSame(1536, $message->size);
        $this->assertEquals($file->expires_at, $message->expiresAt);
    }

    public function test_refuses_to_build_message_for_not_deleted_file(): void
    {
        $this->expectException(LogicException::class);

        $this->factory()->make(StoredFile::factory()->make());
    }

    public function test_rejects_invalid_recipient(): void
    {
        config(['notifications.recipient' => 'not-an-email']);
        $this->app->forgetInstance(FileDeletedMessageFactory::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"not-an-email" is not a valid e-mail address (NOTIFICATION_EMAIL)');

        $this->factory();
    }

    private function factory(): FileDeletedMessageFactory
    {
        return $this->app->make(FileDeletedMessageFactory::class);
    }

    private function deletedFile(DeletionReason $reason): StoredFile
    {
        $file = StoredFile::factory()->make([
            'uuid' => '01a1b24b-5363-737a-be60-d100aeea234c',
            'original_name' => 'Отчёт <final>.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 1536,
            'expires_at' => CarbonImmutable::parse('2026-11-07 12:00:00'),
        ]);
        $file->forceFill([
            'created_at' => CarbonImmutable::parse('2026-11-06 12:00:00'),
            'deleted_at' => CarbonImmutable::parse('2026-11-06 15:30:00'),
            'deletion_reason' => $reason,
        ]);

        return $file;
    }
}
