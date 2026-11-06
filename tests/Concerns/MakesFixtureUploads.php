<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Builds UploadedFile instances from real files in tests/Fixtures, so MIME detection works on real content
 * (Laravel's UploadedFile::fake() reports a MIME type derived from the name, not from the content).
 */
trait MakesFixtureUploads
{
    protected function fixtureUpload(string $fixture, ?string $clientName = null): UploadedFile
    {
        $source = dirname(__DIR__) . '/Fixtures/' . $fixture;
        // Work on a copy: the upload pipeline may move the temporary file.
        $copy = tempnam(sys_get_temp_dir(), 'upload');

        if ($copy === false || ! copy($source, $copy)) {
            throw new RuntimeException(sprintf('Unable to copy fixture "%s".', $fixture));
        }

        return new UploadedFile($copy, $clientName ?? $fixture, null, null, true);
    }
}
