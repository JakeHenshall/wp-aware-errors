<?php

declare(strict_types=1);

$testsDir = getenv('WP_TESTS_DIR');
if (! is_string($testsDir) || $testsDir === '') {
    fwrite(STDERR, "WP_TESTS_DIR is not set. Install the WordPress test library first.\n");
    exit(1);
}
if (! is_file($testsDir . '/includes/functions.php')) {
    fwrite(STDERR, "WordPress test library not found at {$testsDir}.\n");
    exit(1);
}

// Keep the integration harness in a development environment so the plugin's
// renderer is active. Production/staging policy decisions are covered by the
// pure AccessPolicy unit tests and the drop-in policy tests.
if (! defined('WP_ENVIRONMENT_TYPE')) {
    define('WP_ENVIRONMENT_TYPE', 'local');
}
if (getenv('WP_TESTS_MULTISITE') === '1' && ! defined('WP_TESTS_MULTISITE')) {
    define('WP_TESTS_MULTISITE', true);
}
if (! defined('WP_AWARE_ERRORS_DISABLE_COMPOSER_AUTOLOAD')) {
    define('WP_AWARE_ERRORS_DISABLE_COMPOSER_AUTOLOAD', true);
}
if (! defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills');
}

require_once $testsDir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require_once dirname(__DIR__, 2) . '/wp-aware-errors.php';
});

require $testsDir . '/includes/bootstrap.php';
