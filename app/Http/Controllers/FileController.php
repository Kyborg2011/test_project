<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeletionReason;
use App\Models\StoredFile;
use App\Services\Files\FileDeletionService;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\JsonResponse;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Management page: list, download and manual deletion of available files.
 * Deleted and expired files behave as missing (404).
 */
final class FileController extends Controller
{
    public function index(FileSettings $settings): View
    {
        $files = StoredFile::query()
            ->available()
            ->orderByDesc('id')
            ->paginate($settings->perPage);

        return view('files.index', [
            'files' => $files,
            'now' => CarbonImmutable::now(),
        ]);
    }

    public function download(StoredFile $storedFile, FilesystemManager $filesystems, LoggerInterface $logger): StreamedResponse
    {
        abort_unless($storedFile->isAvailable(), Response::HTTP_NOT_FOUND);

        $disk = $filesystems->disk($storedFile->disk);

        if (! $disk instanceof FilesystemAdapter) {
            throw new LogicException(sprintf('Disk "%s" does not support downloads.', $storedFile->disk));
        }

        if (! $disk->exists($storedFile->path)) {
            $logger->warning('Available file is missing on the disk.', ['uuid' => $storedFile->uuid, 'path' => $storedFile->path]);

            abort(Response::HTTP_NOT_FOUND);
        }

        // X-Content-Type-Options: nosniff is added by nginx to every response.
        return $disk->download($storedFile->path, $storedFile->original_name, [
            'Content-Type' => $storedFile->mime_type,
        ]);
    }

    public function destroy(StoredFile $storedFile, FileDeletionService $deletion): JsonResponse
    {
        // false from delete() means another process (e.g. the purge) deleted the file first.
        if (! $storedFile->isAvailable() || ! $deletion->delete($storedFile, DeletionReason::Manual)) {
            return new JsonResponse(
                ['message' => 'The file was not found or has already been deleted.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse(['message' => sprintf('File "%s" deleted.', $storedFile->original_name)]);
    }
}
