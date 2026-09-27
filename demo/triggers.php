<?php

declare(strict_types=1);

/**
 * Real failures used by the admin previews.
 * Each function raises the PHP error it demonstrates. Ignition then renders that throwable.
 */

function wp_aware_demo_null_method(): void
{
    $product = null;
    $product->get_price();
}

function wp_aware_demo_undefined_function(): void
{
    wp_aware_demo_function_that_does_not_exist();
}

function wp_aware_demo_missing_file(): void
{
    @require __DIR__ . '/does-not-exist.php';
}

function wp_aware_demo_missing_class(): void
{
    new WP_Aware_Demo_Missing_Gateway();
}

function wp_aware_demo_undefined_method(): void
{
    (new WP_Aware_Demo_Order())->get_custom_status();
}

function wp_aware_demo_parse_error(): void
{
    include __DIR__ . '/syntax-error.php';
}

function wp_aware_demo_headers_sent(): void
{
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    echo ' ';
    if (! headers_sent()) {
        flush();
    }
    header('X-WP-Aware-Demo: 1');
}

final class WP_Aware_Demo_Order
{
}
