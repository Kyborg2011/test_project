<?php

declare(strict_types=1);

namespace Tests\Feature\Upload;

use App\Models\StoredFile;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\MakesFixtureUploads;
use Tests\TestCase;

final class UploadFileTest extends TestCase
{
    use MakesFixtureUploads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
        config(['files.ttl_minutes' => 1440, 'files.max_size_kb' => 10240]);
        $this->app->forgetInstance(FileSettings::class);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function allowedFilesProvider(): iterable
    {
        yield 'pdf' => ['sample.pdf', 'Report 2026.pdf', 'application/pdf'];
        yield 'docx' => [
            'sample.docx',
            'Letter.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    #[DataProvider('allowedFilesProvider')]
    public function test_uploads_allowed_file(string $fixture, string $clientName, string $mimeType): void
    {
        $upload = $this->fixtureUpload($fixture, $clientName);
        $size = (int) $upload->getSize();

        $response = $this->upload($upload)
            ->assertCreated()
            ->assertJsonPath('message', 'File uploaded.')
            ->assertJsonPath('data.name', $clientName)
            ->assertJsonPath('data.mime_type', $mimeType)
            ->assertJsonPath('data.size', $size)
            ->assertJsonPath('data.uploaded_at', '2026-11-06T12:00:00+00:00')
            ->assertJsonPath('data.expires_at', '2026-11-07T12:00:00+00:00');

        $file = StoredFile::query()->sole();

        $this->assertSame($response->json('data.id'), $file->uuid);
        $this->assertSame($clientName, $file->original_name);
        $this->assertSame($mimeType, $file->mime_type);
        $this->assertSame($size, $file->size);
        $this->assertSame('local', $file->disk);
        $this->assertStringStartsWith('uploads/', $file->path);
        $this->assertNotSame('uploads/' . $clientName, $file->path);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_expiration_follows_configured_ttl(): void
    {
        config(['files.ttl_minutes' => 5]);
        $this->app->forgetInstance(FileSettings::class);

        $this->upload($this->fixtureUpload('sample.pdf'))
            ->assertCreated()
            ->assertJsonPath('data.expires_at', '2026-11-06T12:05:00+00:00');
    }

    public function test_requires_a_file(): void
    {
        $this->postJson(route('files.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Choose a file to upload.']);

        $this->assertNothingStored();
    }

    public function test_rejects_file_larger_than_limit(): void
    {
        config(['files.max_size_kb' => 100]);
        $this->app->forgetInstance(FileSettings::class);

        $this->upload(UploadedFile::fake()->create('big.pdf', 101, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file must not be larger than 100 KB.']);

        $this->assertNothingStored();
    }

    public function test_accepts_file_exactly_at_limit(): void
    {
        config(['files.max_size_kb' => 100]);
        $this->app->forgetInstance(FileSettings::class);

        $this->upload(UploadedFile::fake()->create('limit.pdf', 100, 'application/pdf'))->assertCreated();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function rejectedFilesProvider(): iterable
    {
        yield 'text file' => ['plain.txt', 'notes.txt', 'Only PDF, DOCX files are allowed.'];
        yield 'legacy doc' => ['sample.docx', 'old.doc', 'Only PDF, DOCX files are allowed.'];
        yield 'text renamed to pdf' => ['plain.txt', 'fake.pdf', 'The file content does not match the .pdf extension.'];
        yield 'zip renamed to docx' => ['archive.zip', 'fake.docx', 'The file content does not match the .docx extension.'];
    }

    #[DataProvider('rejectedFilesProvider')]
    public function test_rejects_disallowed_file(string $fixture, string $clientName, string $expectedError): void
    {
        $this->upload($this->fixtureUpload($fixture, $clientName))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => $expectedError]);

        $this->assertNothingStored();
    }

    public function test_rejects_several_files_in_one_request(): void
    {
        $this->postJson(route('files.store'), [
            'file' => [$this->fixtureUpload('sample.pdf'), $this->fixtureUpload('sample.docx')],
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertNothingStored();
    }

    public function test_sanitizes_client_file_name(): void
    {
        $this->upload($this->fixtureUpload('sample.pdf', '../../secret/report.pdf'))
            ->assertCreated()
            ->assertJsonPath('data.name', 'report.pdf');
    }

    /**
     * @return TestResponse<Response>
     */
    private function upload(UploadedFile $file): TestResponse
    {
        return $this->postJson(route('files.store'), ['file' => $file]);
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, StoredFile::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
