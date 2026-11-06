<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\StoredFile;
use App\Services\Files\FileSettings;
use App\Services\Files\FileUploadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\MakesFixtureUploads;
use Tests\TestCase;

final class FileUploadServiceTest extends TestCase
{
    use MakesFixtureUploads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    }

    public function test_stores_file_under_random_name_in_configured_location(): void
    {
        Storage::fake('archive');
        config([
            'filesystems.disks.archive' => ['driver' => 'local', 'root' => storage_path('framework/testing/archive')],
            'files.disk' => 'archive',
            'files.directory' => 'docs/incoming',
            'files.ttl_minutes' => 60,
        ]);
        $this->app->forgetInstance(FileSettings::class);

        $file = $this->service()->store($this->fixtureUpload('sample.docx', 'My Letter.DOCX'));

        $this->assertSame('archive', $file->disk);
        $this->assertSame('docx', $file->extension);
        $this->assertMatchesRegularExpression('~^docs/incoming/[0-9a-f-]{36}\.docx$~', $file->path);
        $this->assertTrue(Str::isUuid(basename($file->path, '.docx')));
        $this->assertSame('My Letter.docx', $file->original_name);
        $this->assertEquals(CarbonImmutable::parse('2026-11-06 13:00:00'), $file->expires_at);
        Storage::disk('archive')->assertExists($file->path);
    }

    public function test_two_uploads_with_same_name_do_not_overwrite_each_other(): void
    {
        Storage::fake('local');

        $first = $this->service()->store($this->fixtureUpload('sample.pdf', 'same.pdf'));
        $second = $this->service()->store($this->fixtureUpload('sample.pdf', 'same.pdf'));

        $this->assertNotSame($first->path, $second->path);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_removes_written_file_when_database_insert_fails(): void
    {
        Storage::fake('local');
        StoredFile::creating(static function (): void {
            throw new RuntimeException('Database is unavailable.');
        });

        try {
            $this->service()->store($this->fixtureUpload('sample.pdf'));
            $this->fail('The exception was not rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database is unavailable.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, StoredFile::query()->count());
    }

    private function service(): FileUploadService
    {
        return $this->app->make(FileUploadService::class);
    }
}
