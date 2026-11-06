<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DownloadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_downloads_file_under_its_original_name(): void
    {
        $file = $this->storedFile(['original_name' => 'Отчёт 2026.pdf', 'mime_type' => 'application/pdf']);

        $response = $this->get(route('files.download', $file))->assertOk();

        $this->assertSame('%PDF-content', $response->streamedContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString(
            "filename*=utf-8''" . rawurlencode('Отчёт 2026.pdf'),
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_expired_file_is_not_downloadable(): void
    {
        $file = StoredFile::factory()->expired()->create();
        Storage::disk('local')->put($file->path, 'content');

        $this->get(route('files.download', $file))->assertNotFound();
    }

    public function test_deleted_file_is_not_downloadable(): void
    {
        $file = StoredFile::factory()->deleted()->create();

        $this->get(route('files.download', $file))->assertNotFound();
    }

    public function test_missing_physical_file_gives_not_found(): void
    {
        $file = StoredFile::factory()->create();

        $this->get(route('files.download', $file))->assertNotFound();
    }

    public function test_unknown_or_malformed_id_gives_not_found(): void
    {
        $this->get('/files/01a1b24b-5363-737a-be60-d100aeea234c/download')->assertNotFound();
        $this->get('/files/1/download')->assertNotFound();
        $this->get('/files/..%2F..%2F.env/download')->assertNotFound();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function storedFile(array $attributes = []): StoredFile
    {
        $file = StoredFile::factory()->create($attributes);
        Storage::disk('local')->put($file->path, '%PDF-content');

        return $file;
    }
}
