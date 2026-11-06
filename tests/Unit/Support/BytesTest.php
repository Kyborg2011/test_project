<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Bytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BytesTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function sizesProvider(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'negative is clamped' => [-5, '0 B'];
        yield 'bytes' => [1023, '1023 B'];
        yield 'one kilobyte' => [1024, '1 KB'];
        yield 'fractional kilobytes' => [1536, '1.5 KB'];
        yield 'rounded to one decimal' => [1100, '1.1 KB'];
        yield 'ten megabytes' => [10 * 1024 * 1024, '10 MB'];
        yield 'gigabytes' => [3 * 1024 ** 3, '3 GB'];
        yield 'largest unit is kept' => [2048 * 1024 ** 4, '2048 TB'];
    }

    #[DataProvider('sizesProvider')]
    public function test_formats_size(int $bytes, string $expected): void
    {
        $this->assertSame($expected, Bytes::format($bytes));
    }
}
