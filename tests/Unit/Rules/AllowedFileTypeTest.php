<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\AllowedFileType;
use App\Services\Files\FileSettings;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\MakesFixtureUploads;

final class AllowedFileTypeTest extends TestCase
{
    use MakesFixtureUploads;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validFilesProvider(): iterable
    {
        yield 'pdf' => ['sample.pdf', 'report.pdf'];
        yield 'docx' => ['sample.docx', 'letter.docx'];
        yield 'upper-case extension' => ['sample.pdf', 'REPORT.PDF'];
    }

    #[DataProvider('validFilesProvider')]
    public function test_accepts_allowed_files(string $fixture, string $clientName): void
    {
        $this->assertNull($this->validate($this->fixtureUpload($fixture, $clientName)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidFilesProvider(): iterable
    {
        yield 'text file' => ['plain.txt', 'notes.txt', 'Only PDF, DOCX files are allowed.'];
        yield 'legacy doc extension' => ['sample.docx', 'letter.doc', 'Only PDF, DOCX files are allowed.'];
        yield 'no extension' => ['sample.pdf', 'report', 'Only PDF, DOCX files are allowed.'];
        yield 'text renamed to pdf' => ['plain.txt', 'fake.pdf', 'The file content does not match the .pdf extension.'];
        yield 'docx renamed to pdf' => ['sample.docx', 'fake.pdf', 'The file content does not match the .pdf extension.'];
        yield 'pdf renamed to docx' => ['sample.pdf', 'fake.docx', 'The file content does not match the .docx extension.'];
        yield 'zip renamed to docx' => ['archive.zip', 'fake.docx', 'The file content does not match the .docx extension.'];
    }

    #[DataProvider('invalidFilesProvider')]
    public function test_rejects_disallowed_files(string $fixture, string $clientName, string $expectedError): void
    {
        $this->assertSame($expectedError, $this->validate($this->fixtureUpload($fixture, $clientName)));
    }

    public function test_rejects_non_file_value(): void
    {
        $this->assertSame('The :attribute must be a file.', $this->validate('not a file'));
    }

    public function test_respects_configured_extensions(): void
    {
        $error = $this->validate($this->fixtureUpload('sample.docx', 'letter.docx'), $this->settings('pdf'));

        $this->assertSame('Only PDF files are allowed.', $error);
    }

    private function validate(mixed $value, ?FileSettings $settings = null): ?string
    {
        $error = null;

        (new AllowedFileType($settings ?? $this->settings()))->validate(
            'file',
            $value,
            function (string $message) use (&$error): PotentiallyTranslatedString {
                $error = $message;

                return new PotentiallyTranslatedString($message, new Translator(new ArrayLoader(), 'en'));
            },
        );

        return $error;
    }

    private function settings(string $extensions = 'pdf,docx'): FileSettings
    {
        return FileSettings::fromConfig([
            'disk' => 'local',
            'directory' => 'uploads',
            'max_size_kb' => 10240,
            'allowed_extensions' => $extensions,
            'ttl_minutes' => 1440,
            'per_page' => 20,
            'purge_batch_size' => 100,
            'mime_types' => [
                'pdf' => ['application/pdf'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ],
        ]);
    }
}
