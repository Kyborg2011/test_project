<?php

declare(strict_types=1);

namespace App\Messaging;

final readonly class RelayResult
{
    public function __construct(
        public int $published,
        public bool $failed,
    ) {
    }
}
