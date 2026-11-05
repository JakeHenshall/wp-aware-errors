<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

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
        Admin::register();

        if (! Gate::handlingEnabled()) {
            return;
        }

        // Expensive diagnostics only run when detailed output is technically
        // possible. Production/staging without opt-in still gets a safe generic
        // page and a correlation reference.
        if (Gate::diagnosticsEnabled()) {
            HookRecorder::register();
            RequestInspector::register();
        }

        ErrorHandler::register();
    }
}
