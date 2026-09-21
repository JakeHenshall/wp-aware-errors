<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class Bootstrap
{
    private static bool $booted = false;

    public static function boot(string $entryFile): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        Runtime::setInstallation(Installation::detect($entryFile));

        // Metadata/admin features remain available even when the pretty renderer is gated off.
        PluginThemeIndex::primeLater();
        Admin::register();

        if (! Gate::enabled()) {
            return;
        }

        HookRecorder::register();
        RequestInspector::register();
        ErrorHandler::register();
    }
}
