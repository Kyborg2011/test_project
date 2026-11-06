<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DeletionReason;
use PHPUnit\Framework\TestCase;

final class DeletionReasonTest extends TestCase
{
    public function test_values_are_stable_for_storage_and_messages(): void
    {
        $this->assertSame('manual', DeletionReason::Manual->value);
        $this->assertSame('expired', DeletionReason::Expired->value);
    }

    public function test_has_human_readable_labels(): void
    {
        $this->assertSame('deleted manually', DeletionReason::Manual->label());
        $this->assertSame('expired', DeletionReason::Expired->label());
    }
}
