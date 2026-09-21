<?php

declare(strict_types=1);

/**
 * Lightweight PSR-4 autoloader for the plugin build.
 * Composer users can use the identical mapping from composer.json instead.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Hensh\\WpAwareErrors\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '' || str_contains($relative, '..')) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});
