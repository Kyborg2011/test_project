<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Files;

use App\Services\Files\OriginalFileName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OriginalFileNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function namesProvider(): iterable
    {
        yield 'plain name' => ['report.pdf', 'pdf', 'report.pdf'];
        yield 'unicode is kept' => ['Отчёт 2026.docx', 'docx', 'Отчёт 2026.docx'];
        yield 'extension is lower-cased' => ['REPORT.PDF', 'PDF', 'REPORT.pdf'];
        yield 'unix path is removed' => ['../../etc/passwd.pdf', 'pdf', 'passwd.pdf'];
        yield 'windows path is removed' => ['C:\\fakepath\\report.pdf', 'pdf', 'report.pdf'];
        yield 'control characters are removed' => ["rep\x00ort\x1F.pdf", 'pdf', 'report.pdf'];
        yield 'right-to-left override is removed' => ["invoice\u{202E}fdp.exe.pdf", 'pdf', 'invoicefdp.exe.pdf'];
        yield 'whitespace is collapsed' => ["  my \t  file  .pdf", 'pdf', 'my file.pdf'];
        yield 'empty base name' => ['.pdf', 'pdf', 'file.pdf'];
        yield 'only dots and spaces' => [' . .pdf', 'pdf', 'file.pdf'];
        yield 'invalid utf-8 is replaced' => ["bad\xFFname.pdf", 'pdf', 'bad?name.pdf'];
    }

    #[DataProvider('namesProvider')]
    public function test_sanitizes_name(string $name, string $extension, string $expected): void
    {
        $this->assertSame($expected, OriginalFileName::sanitize($name, $extension));
    }

    public function test_long_name_is_truncated_keeping_extension(): void
    {
        $result = OriginalFileName::sanitize(str_repeat('я', 300) . '.docx', 'docx');

        $this->assertSame(OriginalFileName::MAX_LENGTH, mb_strlen($result));
        $this->assertStringEndsWith('я.docx', $result);
    }
}
