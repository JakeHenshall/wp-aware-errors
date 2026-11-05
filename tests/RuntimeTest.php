<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\Runtime;
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        State::resetRuntime();
    }

    protected function tearDown(): void
    {
        State::resetRuntime();
    }

    public function testHandlingAndRenderedGuardsPreventReentry(): void
    {
        self::assertTrue(Runtime::beginHandling());
        self::assertTrue(Runtime::handling());
        self::assertFalse(Runtime::beginHandling());

        Runtime::markRendered();
        Runtime::endHandling();

        self::assertTrue(Runtime::rendered());
        self::assertFalse(Runtime::handling());
        self::assertFalse(Runtime::beginHandling());
    }

    public function testWordPressFatalOutputMarkerCanBeCleared(): void
    {
        self::assertFalse(Runtime::wordPressFatalOutput());
        Runtime::markWordPressFatalOutput();
        self::assertTrue(Runtime::wordPressFatalOutput());
        Runtime::clearWordPressFatalOutput();
        self::assertFalse(Runtime::wordPressFatalOutput());
    }
}
