<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ExceptionData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionDataTest extends TestCase
{
    public function testBuildsTraceFromThrowable(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('Allowed memory size exhausted'));

        self::assertSame(RuntimeException::class, $error->type);
        self::assertSame('Allowed memory size exhausted', $error->message);
        self::assertSame(__FILE__, $error->file);
        self::assertGreaterThan(0, $error->line);
        self::assertSame('{throw}', $error->trace[0]['function']);
        self::assertTrue($error->isMemoryExhausted());
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $error->time);
    }

    public function testDoesNotFlagUnrelatedMessagesAsMemoryErrors(): void
    {
        self::assertFalse(ExceptionData::fromThrowable(new RuntimeException('boom'))->isMemoryExhausted());
    }
}
