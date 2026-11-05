<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class Runtime
{
    private static ?Installation $installation = null;
    private static bool $rendered = false;
    private static bool $handling = false;
    private static bool $wordPressFatalOutput = false;
    /** @var array<string,mixed> */
    private static array $requestContext = [];

    public static function setInstallation(Installation $installation): void { self::$installation = $installation; }
    public static function installation(): Installation
    {
        return self::$installation ?? new Installation('unknown', '', '', 'Unknown');
    }
    public static function rendered(): bool { return self::$rendered; }
    public static function markRendered(): void { self::$rendered = true; }
    public static function handling(): bool { return self::$handling; }
    public static function beginHandling(): bool
    {
        if (self::$rendered || self::$handling) {
            return false;
        }
        self::$handling = true;
        return true;
    }
    public static function endHandling(): void { self::$handling = false; }
    public static function wordPressFatalOutput(): bool { return self::$wordPressFatalOutput; }
    public static function markWordPressFatalOutput(): void { self::$wordPressFatalOutput = true; }
    public static function clearWordPressFatalOutput(): void { self::$wordPressFatalOutput = false; }
    /** @param array<string,mixed> $context */
    public static function mergeRequestContext(array $context): void { self::$requestContext = array_merge(self::$requestContext, $context); }
    /** @return array<string,mixed> */
    public static function requestContext(): array { return self::$requestContext; }
}
