<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\AccessPolicy;
use PHPUnit\Framework\TestCase;

final class AccessPolicyTest extends TestCase
{
    public function testAdministratorsGetDetailsInLocalAndDevelopment(): void
    {
        self::assertTrue(AccessPolicy::decide('local', true, false, false, false, true));
        self::assertTrue(AccessPolicy::decide('development', true, false, false, false, true));
    }

    public function testStagingAdministratorsNeedExplicitEnablement(): void
    {
        self::assertFalse(AccessPolicy::decide('staging', true, false, false, false, true));
        self::assertTrue(AccessPolicy::decide('staging', true, true, false, false, true));
    }

    public function testProductionAdministratorsNeedBothOptIns(): void
    {
        self::assertFalse(AccessPolicy::decide('production', true, false, true, false, true));
        self::assertFalse(AccessPolicy::decide('production', true, true, false, false, true));
        self::assertTrue(AccessPolicy::decide('production', true, true, true, false, true));
    }

    public function testAnonymousVisitorsOnlyGetDetailsInLocalOrDevelopmentWithOptIn(): void
    {
        self::assertFalse(AccessPolicy::decide('local', false, true, false, false, true));
        self::assertTrue(AccessPolicy::decide('local', false, true, false, true, true));
        self::assertTrue(AccessPolicy::decide('development', false, false, false, true, true));
        self::assertFalse(AccessPolicy::decide('staging', false, true, true, true, true));
        self::assertFalse(AccessPolicy::decide('production', false, true, true, true, true));
    }

    public function testGateAndUnknownEnvironmentFailClosed(): void
    {
        self::assertFalse(AccessPolicy::decide('local', true, true, true, true, false));
        self::assertFalse(AccessPolicy::decide('weird', true, false, false, true, true));
        self::assertFalse(AccessPolicy::decide('weird', false, true, true, true, true));
    }
}
