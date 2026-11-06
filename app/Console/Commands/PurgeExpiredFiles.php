<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeletionReason;
use App\Models\StoredFile;
use App\Services\Files\FileDeletionService;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Deletes files whose lifetime is over. Runs every minute from the scheduler (routes/console.php).
 */
final class PurgeExpiredFiles extends Command
{
    protected $signature = 'files:purge-expired';

    protected $description = 'Delete files whose lifetime (FILES_TTL_MINUTES) is over and notify about each one';

    public function handle(FileDeletionService $deletion, FileSettings $settings, LoggerInterface $logger): int
    {
        // One fixed moment for the whole run, so the set of files does not grow while it is processed.
        $now = CarbonImmutable::now();
        $deleted = 0;
        $failed = 0;

        // chunkById pages by "id > last id", so rows changed in a batch never shift the next page,
        // and a file that failed to delete is not picked up again in this run.
        StoredFile::query()
            ->expired($now)
            ->chunkById($settings->purgeBatchSize, function (Collection $files) use ($deletion, $logger, &$deleted, &$failed): void {
                /** @var Collection<int, StoredFile> $files */
                foreach ($files as $file) {
                    try {
                        if ($deletion->delete($file, DeletionReason::Expired)) {
                            $deleted++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        $logger->error('Unable to delete an expired file.', [
                            'uuid' => $file->uuid,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        if ($deleted === 0 && $failed === 0) {
            $this->components->info('No expired files.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Deleted expired files: %d.', $deleted));

        if ($failed > 0) {
            $this->components->error(sprintf('Failed to delete: %d (see the log). They will be retried on the next run.', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
