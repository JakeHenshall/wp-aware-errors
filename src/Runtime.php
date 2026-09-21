<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class Runtime
{
    private static ?Installation $installation = null;
    private static bool $rendered = false;
    /** @var array<string,mixed> */
    private static array $requestContext = [];

    public static function setInstallation(Installation $installation): void { self::$installation = $installation; }
    public static function installation(): Installation
    {
        return self::$installation ?? new Installation('unknown', '', '', 'Unknown');
    }
    public static function rendered(): bool { return self::$rendered; }
    public static function markRendered(): void { self::$rendered = true; }
    /** @param array<string,mixed> $context */
    public static function mergeRequestContext(array $context): void { self::$requestContext = array_merge(self::$requestContext, $context); }
    /** @return array<string,mixed> */
    public static function requestContext(): array { return self::$requestContext; }
}
