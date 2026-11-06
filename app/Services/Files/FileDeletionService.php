<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\DeletionReason;
use App\Messaging\Messages\FileDeletedMessage;
use App\Messaging\Messages\FileDeletedMessageFactory;
use App\Messaging\Outbox;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The single place where files are deleted, both manually and after expiration.
 *
 * 1. In one transaction: the record is marked as deleted (only if it is not deleted yet)
 *    and the e-mail message is written to the outbox. Either both happen or neither does.
 * 2. After the commit the physical file is removed. A failure here is logged and does not
 *    undo the deletion: the file is already unavailable and the notification is guaranteed.
 */
final readonly class FileDeletionService
{
    public function __construct(
        private DatabaseManager $db,
        private Outbox $outbox,
        private FileDeletedMessageFactory $messages,
        private FilesystemFactory $filesystems,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool true if this call deleted the file; false if it had already been deleted
     *              (for example, concurrently by the purge and by a user) - no second notification then
     */
    public function delete(StoredFile $file, DeletionReason $reason): bool
    {
        $deletedAt = CarbonImmutable::now();

        $deleted = $this->db->transaction(function () use ($file, $reason, $deletedAt): bool {
            // Atomic "compare and set": of two concurrent calls only one updates the row.
            $affected = StoredFile::query()
                ->whereKey($file->getKey())
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $deletedAt,
                    'deletion_reason' => $reason->value,
                    'updated_at' => $deletedAt,
                ]);

            if ($affected === 0) {
                return false;
            }

            $file->forceFill([
                'deleted_at' => $deletedAt,
                'deletion_reason' => $reason,
                'updated_at' => $deletedAt,
            ])->syncOriginal();

            $this->outbox->record(FileDeletedMessage::TYPE, $this->messages->make($file)->toArray());

            return true;
        });

        if (! $deleted) {
            $this->logger->info('File was already deleted.', ['uuid' => $file->uuid]);

            return false;
        }

        $this->removePhysicalFile($file);

        $this->logger->info('File deleted.', ['uuid' => $file->uuid, 'reason' => $reason->value]);

        return true;
    }

    private function removePhysicalFile(StoredFile $file): void
    {
        try {
            $this->filesystems->disk($file->disk)->delete($file->path);
        } catch (Throwable $exception) {
            $this->logger->warning('Unable to remove the physical file; it is left on the disk.', [
                'uuid' => $file->uuid,
                'disk' => $file->disk,
                'path' => $file->path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
