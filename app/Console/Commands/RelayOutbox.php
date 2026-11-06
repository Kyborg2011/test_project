<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsAsWorker;
use App\Messaging\OutboxRelay;
use App\Messaging\OutboxSettings;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Long-running relay (the "relay" container): publishes outbox messages to RabbitMQ.
 */
final class RelayOutbox extends Command
{
    use RunsAsWorker;

    protected $signature = 'outbox:relay {--once : Publish one batch and exit}';

    protected $description = 'Publish pending outbox messages to RabbitMQ';

    public function handle(OutboxRelay $relay, OutboxSettings $settings, LoggerInterface $logger): int
    {
        if ($this->option('once')) {
            $result = $relay->relayBatch();
            $this->log(sprintf('Published: %d.%s', $result->published, $result->failed ? ' Publishing failed, see the log.' : ''));

            return $result->failed ? self::FAILURE : self::SUCCESS;
        }

        $this->listenForStopSignals();
        $this->log('Outbox relay started.');

        while (! $this->shouldStop()) {
            try {
                $result = $relay->relayBatch();
            } catch (Throwable $exception) {
                // For example, the database is restarting: keep the worker alive and try again.
                $logger->error('Outbox relay pass failed.', ['error' => $exception->getMessage()]);
                $this->log('Pass failed: ' . $exception->getMessage());
                $this->pause($settings->pollIntervalMs);

                continue;
            }

            if ($result->published > 0) {
                $this->log(sprintf('Published: %d.', $result->published));
            }

            if ($result->failed) {
                $this->log('Publishing failed (broker unavailable?), the message will be retried later.');
            }

            // A full batch means there may be more messages waiting: continue without a pause.
            if ($result->failed || $result->published < $settings->batchSize) {
                $this->pause($settings->pollIntervalMs);
            }
        }

        $this->log('Outbox relay stopped.');

        return self::SUCCESS;
    }
}
