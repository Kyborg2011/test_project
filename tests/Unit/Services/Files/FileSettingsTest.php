<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Files;

use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileSettingsTest extends TestCase
{
    private const string DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function test_builds_from_env_like_string_values(): void
    {
        $settings = FileSettings::fromConfig($this->config());

        $this->assertSame('local', $settings->disk);
        $this->assertSame('uploads', $settings->directory);
        $this->assertSame(10240, $settings->maxSizeKb);
        $this->assertSame(['pdf', 'docx'], $settings->allowedExtensions);
        $this->assertSame(1440, $settings->ttlMinutes);
        $this->assertSame(20, $settings->perPage);
        $this->assertSame(100, $settings->purgeBatchSize);
    }

    public function test_normalises_extensions_list(): void
    {
        $settings = FileSettings::fromConfig($this->config(['allowed_extensions' => ' PDF , docx,,pdf ']));

        $this->assertSame(['pdf', 'docx'], $settings->allowedExtensions);
    }

    public function test_keeps_mime_types_only_for_allowed_extensions(): void
    {
        $settings = FileSettings::fromConfig($this->config(['allowed_extensions' => 'pdf']));

        $this->assertSame(['pdf' => ['application/pdf']], $settings->mimeTypes);
        $this->assertSame(['application/pdf'], $settings->allowedMimeTypes());
    }

    public function test_trims_slashes_from_directory(): void
    {
        $settings = FileSettings::fromConfig($this->config(['directory' => '/files/uploads/']));

        $this->assertSame('files/uploads', $settings->directory);
    }

    public function test_converts_max_size_to_bytes(): void
    {
        $settings = FileSettings::fromConfig($this->config(['max_size_kb' => '2']));

        $this->assertSame(2048, $settings->maxSizeBytes());
    }

    public function test_calculates_expiration_from_ttl(): void
    {
        $settings = FileSettings::fromConfig($this->config(['ttl_minutes' => '90']));
        $uploadedAt = CarbonImmutable::parse('2026-01-01 10:00:00');

        $this->assertEquals(CarbonImmutable::parse('2026-01-01 11:30:00'), $settings->expiresAt($uploadedAt));
    }

    public function test_checks_extension_and_mime_type_pair(): void
    {
        $settings = FileSettings::fromConfig($this->config());

        $this->assertTrue($settings->isAllowed('pdf', 'application/pdf'));
        $this->assertTrue($settings->isAllowed('DOCX', self::DOCX_MIME));
        $this->assertTrue($settings->isAllowed('docx', 'application/zip'));
        $this->assertFalse($settings->isAllowed('pdf', self::DOCX_MIME));
        $this->assertFalse($settings->isAllowed('doc', 'application/msword'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'zero ttl' => [['ttl_minutes' => '0'], '"ttl_minutes" must be greater than zero'];
        yield 'negative max size' => [['max_size_kb' => '-1'], '"max_size_kb" must be greater than zero'];
        yield 'non-numeric per page' => [['per_page' => 'abc'], '"per_page" must be an integer'];
        yield 'fractional batch size' => [['purge_batch_size' => '1.5'], '"purge_batch_size" must be an integer'];
        yield 'missing value' => [['ttl_minutes' => null], '"ttl_minutes" must be an integer'];
        yield 'empty disk' => [['disk' => ''], '"disk" must not be empty'];
        yield 'empty directory' => [['directory' => '/'], '"directory" must not be empty'];
        yield 'no extensions' => [['allowed_extensions' => ' , '], '"allowed_extensions" must not be empty'];
        yield 'unknown extension' => [['allowed_extensions' => 'pdf,exe'], 'no MIME types are defined for extension "exe"'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidConfigProvider')]
    public function test_rejects_invalid_config(array $override, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        FileSettings::fromConfig($this->config($override));
    }

    /**
     * Mimics config('files'): values from .env arrive as strings.
     *
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function config(array $override = []): array
    {
        return array_replace([
            'disk' => 'local',
            'directory' => 'uploads',
            'max_size_kb' => '10240',
            'allowed_extensions' => 'pdf,docx',
            'ttl_minutes' => '1440',
            'per_page' => '20',
            'purge_batch_size' => '100',
            'mime_types' => [
                'pdf' => ['application/pdf'],
                'docx' => [self::DOCX_MIME, 'application/zip'],
            ],
        ], $override);
    }
}
