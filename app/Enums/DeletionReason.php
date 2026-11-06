<?php

declare(strict_types=1);

namespace App\Enums;

enum DeletionReason: string
{
    case Manual = 'manual';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'deleted manually',
            self::Expired => 'expired',
        };
    }
}
