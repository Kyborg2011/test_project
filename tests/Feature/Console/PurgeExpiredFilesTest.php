<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\DeletionReason;
use App\Models\OutboxMessage;
use App\Models\StoredFile;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\TestCase;

final class PurgeExpiredFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    }

    public function test_deletes_only_expired_files_and_notifies_about_each(): void
    {
        $expired = $this->storedFile(CarbonImmutable::now()->subSecond());
        $expiresNow = $this->storedFile(CarbonImmutable::now());
        $active = $this->storedFile(CarbonImmutable::now()->addSecond());
        $alreadyDeleted = StoredFile::factory()->expired()->deleted(DeletionReason::Manual)->create();

        $this->purge()
            ->expectsOutputToContain('Deleted expired files: 2.')
            ->assertSuccessful();

        foreach ([$expired, $expiresNow] as $file) {
            $file->refresh();
            $this->assertSame(DeletionReason::Expired, $file->deletion_reason);
            Storage::disk('local')->assertMissing($file->path);
        }

        $this->assertFalse($active->refresh()->isDeleted());
        Storage::disk('local')->assertExists($active->path);
        $this->assertSame(DeletionReason::Manual, $alreadyDeleted->refresh()->deletion_reason);

        $notified = OutboxMessage::query()->get()->map(fn (OutboxMessage $m): mixed => $m->payload['payload']['file_id']);
        $this->assertEqualsCanonicalizing([$expired->uuid, $expiresNow->uuid], $notified->all());
    }

    public function test_processes_all_files_in_several_batches(): void
    {
        config(['files.purge_batch_size' => 2]);
        $this->app->forgetInstance(FileSettings::class);

        for ($i = 0; $i < 5; $i++) {
            $this->storedFile(CarbonImmutable::now()->subMinutes($i + 1));
        }

        $this->purge()
            ->expectsOutputToContain('Deleted expired files: 5.')
            ->assertSuccessful();

        $this->assertSame(0, StoredFile::query()->expired()->count());
        $this->assertSame(5, OutboxMessage::query()->count());
    }

    public function test_reports_when_there_is_nothing_to_delete(): void
    {
        $this->storedFile(CarbonImmutable::now()->addDay());

        $this->purge()
            ->expectsOutputToContain('No expired files.')
            ->assertSuccessful();

        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_failure_of_one_file_does_not_stop_the_others(): void
    {
        $broken = $this->storedFile(CarbonImmutable::now()->subMinutes(2));
        $fine = $this->storedFile(CarbonImmutable::now()->subMinute());
        OutboxMessage::creating(static function (OutboxMessage $message) use ($broken): void {
            if ($message->payload['payload']['file_id'] === $broken->uuid) {
                throw new RuntimeException('Outbox insert failed.');
            }
        });

        $this->purge()
            ->expectsOutputToContain('Deleted expired files: 1.')
            ->expectsOutputToContain('Failed to delete: 1')
            ->assertFailed();

        $this->assertTrue($fine->refresh()->isDeleted());
        // The failed file stays expired (not deleted) and will be picked up by the next run.
        $this->assertFalse($broken->refresh()->isDeleted());
        Storage::disk('local')->assertExists($broken->path);
        $this->assertSame([$broken->id], StoredFile::query()->expired()->pluck('id')->all());
    }

    public function test_is_scheduled_every_minute_without_overlapping(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, 'files:purge-expired'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    private function purge(): PendingCommand
    {
        $command = $this->artisan('files:purge-expired');
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    private function storedFile(CarbonImmutable $expiresAt): StoredFile
    {
        $file = StoredFile::factory()->create(['expires_at' => $expiresAt]);
        Storage::disk($file->disk)->put($file->path, 'content');

        return $file;
    }
}
