<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\Gate;
use PHPUnit\Framework\TestCase;

final class GateTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['wp_test_environment'] = 'local';
    }

    public function testLocalEnvironmentIsEnabledByDefault(): void
    {
        $GLOBALS['wp_test_environment'] = 'local';

        self::assertTrue(Gate::enabled());
    }

    public function testProductionRequiresBothExplicitFlags(): void
    {
        $GLOBALS['wp_test_environment'] = 'production';

        self::assertFalse(Gate::enabled());
        self::assertFalse(Gate::decide('production', true, false, true));
        self::assertFalse(Gate::decide('production', null, true, true));
        self::assertTrue(Gate::decide('production', true, true, false));
    }

    public function testDisabledFlagAlwaysWins(): void
    {
        self::assertFalse(Gate::decide('local', false, true, true));
        self::assertFalse(Gate::decide('production', false, true, true));
    }

    public function testNonProductionEnvironments(): void
    {
        self::assertTrue(Gate::decide('staging', null, false, true));
        self::assertFalse(Gate::decide('staging', null, false, false));
        self::assertTrue(Gate::decide('staging', true, false, false));
        self::assertTrue(Gate::decide('development', null, false, false));
    }
}
