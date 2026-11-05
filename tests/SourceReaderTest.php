<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\SourceReader;
use PHPUnit\Framework\TestCase;

final class SourceReaderTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'wpa-source-') ?: '';
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = 'line ' . $i;
        }
        file_put_contents($this->file, implode("\n", $lines));
    }

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testReadsARadiusAroundTheActiveLine(): void
    {
        $rows = SourceReader::around($this->file, 10, 2);

        self::assertCount(5, $rows);
        self::assertSame(8, $rows[0]['line']);
        self::assertSame(12, $rows[4]['line']);
        self::assertTrue($rows[2]['active']);
        self::assertSame('line 10', $rows[2]['text']);
    }

    public function testReturnsNothingForInvalidInput(): void
    {
        self::assertSame([], SourceReader::around('', 1));
        self::assertSame([], SourceReader::around('/does/not/exist.php', 1));
        self::assertSame([], SourceReader::around($this->file, 0));
    }
}
