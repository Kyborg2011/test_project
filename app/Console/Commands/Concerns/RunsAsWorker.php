<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Carbon\CarbonImmutable;

/**
 * Graceful stop for long-running commands: SIGTERM (docker stop) / SIGINT (Ctrl+C) finish the current
 * unit of work instead of killing the process in the middle of it.
 */
trait RunsAsWorker
{
    private bool $shouldStop = false;

    protected function listenForStopSignals(): void
    {
        if (extension_loaded('pcntl')) {
            $this->trap([SIGTERM, SIGINT], function (): void {
                $this->shouldStop = true;
            });
        }
    }

    protected function stop(): void
    {
        $this->shouldStop = true;
    }

    protected function shouldStop(): bool
    {
        return $this->shouldStop;
    }

    /**
     * Sleeps in small steps so that a stop signal is handled without waiting for the whole pause.
     */
    protected function pause(int $milliseconds): void
    {
        $until = microtime(true) + $milliseconds / 1000;

        while (! $this->shouldStop && microtime(true) < $until) {
            usleep(100_000);
        }
    }

    protected function log(string $message): void
    {
        $this->line(sprintf('[%s] %s', CarbonImmutable::now()->format('Y-m-d H:i:s'), $message));
    }
}
