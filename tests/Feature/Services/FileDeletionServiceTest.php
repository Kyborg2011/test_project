<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DeletionReason;
use App\Messaging\Messages\FileDeletedMessageFactory;
use App\Models\OutboxMessage;
use App\Models\StoredFile;
use App\Services\Files\FileDeletionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class FileDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
        config(['notifications.recipient' => 'ops@example.com']);
        $this->app->forgetInstance(FileDeletedMessageFactory::class);
    }

    public function test_marks_file_deleted_removes_it_from_disk_and_records_notification(): void
    {
        $file = $this->storedFile();

        $this->assertTrue($this->service()->delete($file, DeletionReason::Manual));

        // The passed instance is updated in place.
        $this->assertSame(DeletionReason::Manual, $file->deletion_reason);
        $this->assertEquals(CarbonImmutable::now(), $file->deleted_at);

        $file->refresh();
        $this->assertTrue($file->isDeleted());
        $this->assertSame(DeletionReason::Manual, $file->deletion_reason);
        Storage::disk('local')->assertMissing($file->path);

        $message = OutboxMessage::query()->sole();
        $this->assertSame('file.deleted', $message->type);
        $this->assertSame('ops@example.com', $message->payload['email']['to']);
        $this->assertSame($file->uuid, $message->payload['payload']['file_id']);
        $this->assertSame('manual', $message->payload['payload']['reason']);
        $this->assertSame('2026-11-06T12:00:00+00:00', $message->payload['payload']['deleted_at']);
    }

    public function test_records_expired_reason(): void
    {
        $file = $this->storedFile();

        $this->service()->delete($file, DeletionReason::Expired);

        $this->assertSame(DeletionReason::Expired, $file->refresh()->deletion_reason);
        $this->assertSame('expired', OutboxMessage::query()->sole()->payload['payload']['reason']);
    }

    public function test_second_deletion_is_a_no_op_without_second_notification(): void
    {
        $file = $this->storedFile();

        $this->assertTrue($this->service()->delete($file, DeletionReason::Manual));
        $this->assertFalse($this->service()->delete($file, DeletionReason::Expired));

        $this->assertSame(DeletionReason::Manual, $file->refresh()->deletion_reason);
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_concurrent_deletion_from_stale_instance_sends_one_notification(): void
    {
        $file = $this->storedFile();
        // Two processes (a user and the purge) loaded the same file before either deleted it.
        $loadedByUser = StoredFile::query()->findOrFail($file->id);
        $loadedByPurge = StoredFile::query()->findOrFail($file->id);

        $this->assertTrue($this->service()->delete($loadedByUser, DeletionReason::Manual));
        $this->assertFalse($this->service()->delete($loadedByPurge, DeletionReason::Expired));

        $this->assertSame(DeletionReason::Manual, $file->refresh()->deletion_reason);
        $this->assertSame(1, OutboxMessage::query()->count());
        // The stale instance is not changed by the failed attempt.
        $this->assertNull($loadedByPurge->deleted_at);
    }

    public function test_nothing_changes_when_outbox_write_fails(): void
    {
        $file = $this->storedFile();
        OutboxMessage::creating(static function (): void {
            throw new RuntimeException('Outbox insert failed.');
        });

        try {
            $this->service()->delete($file, DeletionReason::Manual);
            $this->fail('The exception was not rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Outbox insert failed.', $exception->getMessage());
        }

        // The transaction was rolled back: the file is still available and still on the disk.
        $this->assertFalse($file->refresh()->isDeleted());
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_disk_failure_does_not_undo_deletion(): void
    {
        $file = $this->storedFile();
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->with($file->path)->andThrow(new RuntimeException('Read-only file system'));
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->with('local')->andReturn($disk);
        $this->app->instance(FilesystemFactory::class, $filesystems);
        $log = Log::spy();

        $this->assertTrue($this->service()->delete($file, DeletionReason::Manual));

        $this->assertTrue($file->refresh()->isDeleted());
        $this->assertSame(1, OutboxMessage::query()->count());
        $log->shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['path'] === $file->path
                && $context['error'] === 'Read-only file system');
    }

    public function test_already_missing_physical_file_is_not_an_error(): void
    {
        $file = $this->storedFile();
        Storage::disk('local')->delete($file->path);

        $this->assertTrue($this->service()->delete($file, DeletionReason::Expired));
        $this->assertSame(1, OutboxMessage::query()->count());
    }

    private function service(): FileDeletionService
    {
        return $this->app->make(FileDeletionService::class);
    }

    private function storedFile(): StoredFile
    {
        $file = StoredFile::factory()->create();
        Storage::disk($file->disk)->put($file->path, 'content');

        return $file;
    }
}
