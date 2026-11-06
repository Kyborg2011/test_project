<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Enums\DeletionReason;
use App\Models\OutboxMessage;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DeleteFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_deletes_file_manually_and_records_notification(): void
    {
        $file = StoredFile::factory()->create(['original_name' => 'report.pdf']);
        Storage::disk('local')->put($file->path, 'content');

        $this->deleteJson(route('files.destroy', $file))
            ->assertOk()
            ->assertExactJson(['message' => 'File "report.pdf" deleted.']);

        $file->refresh();
        $this->assertSame(DeletionReason::Manual, $file->deletion_reason);
        Storage::disk('local')->assertMissing($file->path);

        $message = OutboxMessage::query()->sole();
        $this->assertSame($file->uuid, $message->payload['payload']['file_id']);
        $this->assertSame('manual', $message->payload['payload']['reason']);
    }

    public function test_deleted_file_disappears_from_list(): void
    {
        $file = StoredFile::factory()->create(['original_name' => 'gone.pdf']);

        $this->deleteJson(route('files.destroy', $file))->assertOk();

        $this->get(route('files.index'))->assertDontSee('gone.pdf');
    }

    public function test_second_deletion_gives_not_found_without_second_notification(): void
    {
        $file = StoredFile::factory()->create();

        $this->deleteJson(route('files.destroy', $file))->assertOk();
        $this->deleteJson(route('files.destroy', $file))
            ->assertNotFound()
            ->assertJsonPath('message', 'The file was not found or has already been deleted.');

        $this->assertSame(1, OutboxMessage::query()->count());
    }

    public function test_expired_file_is_left_to_the_purge(): void
    {
        $file = StoredFile::factory()->expired()->create();

        $this->deleteJson(route('files.destroy', $file))->assertNotFound();

        $this->assertFalse($file->refresh()->isDeleted());
        $this->assertSame(0, OutboxMessage::query()->count());
    }

    public function test_unknown_file_gives_not_found(): void
    {
        $this->deleteJson('/files/01a1b24b-5363-737a-be60-d100aeea234c')->assertNotFound();
        $this->deleteJson('/files/not-a-uuid')->assertNotFound();
    }

    public function test_get_request_does_not_delete(): void
    {
        $file = StoredFile::factory()->create();

        $this->get('/files/' . $file->uuid)->assertMethodNotAllowed();

        $this->assertFalse($file->refresh()->isDeleted());
    }
}
