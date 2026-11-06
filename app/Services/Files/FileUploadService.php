<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final readonly class FileUploadService
{
    public function __construct(
        private FileSettings $settings,
        private FilesystemFactory $filesystems,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Saves an already validated file to the disk and registers it in the database.
     * The file is written first: if the database insert fails, the written file is removed,
     * so there is never a record pointing to a missing file.
     */
    public function store(UploadedFile $file): StoredFile
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $disk = $this->filesystems->disk($this->settings->disk);

        // The stored name is random: the client name is never used as a path.
        $path = $disk->putFileAs($this->settings->directory, $file, Str::uuid()->toString() . '.' . $extension);

        if ($path === false) {
            throw new RuntimeException('Unable to write the uploaded file to the disk.');
        }

        try {
            $storedFile = StoredFile::query()->create([
                'original_name' => OriginalFileName::sanitize($file->getClientOriginalName(), $extension),
                'disk' => $this->settings->disk,
                'path' => $path,
                'extension' => $extension,
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'expires_at' => $this->settings->expiresAt(CarbonImmutable::now()),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        $this->logger->info('File uploaded.', [
            'uuid' => $storedFile->uuid,
            'path' => $path,
            'size' => $storedFile->size,
            'expires_at' => $storedFile->expires_at->toIso8601String(),
        ]);

        return $storedFile;
    }
}
