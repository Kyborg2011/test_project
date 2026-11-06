<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Duration;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function remainingProvider(): iterable
    {
        yield 'in the past' => [-10, 'expired'];
        yield 'exactly now' => [0, 'expired'];
        yield 'seconds' => [59, '< 1 min'];
        yield 'one minute' => [60, '1 min'];
        yield 'minutes' => [45 * 60 + 30, '45 min'];
        yield 'whole hours' => [2 * 3600, '2 h'];
        yield 'hours and minutes' => [3 * 3600 + 5 * 60, '3 h 5 min'];
        yield 'just under a day' => [1439 * 60, '23 h 59 min'];
        yield 'one day' => [1440 * 60, '1 d'];
        yield 'two largest units only' => [86400 + 2 * 3600 + 7 * 60, '1 d 2 h'];
        yield 'days and minutes' => [2 * 86400 + 7 * 60, '2 d 7 min'];
    }

    #[DataProvider('remainingProvider')]
    public function test_formats_time_left(int $seconds, string $expected): void
    {
        $now = new DateTimeImmutable('2026-11-06 12:00:00');

        $this->assertSame($expected, Duration::remaining($now, $now->modify(sprintf('%+d seconds', $seconds))));
    }
}
