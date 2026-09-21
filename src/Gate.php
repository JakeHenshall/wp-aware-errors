<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class Gate
{
    public static function enabled(): bool
    {
        if (defined('WP_AWARE_ERRORS_ENABLED') && WP_AWARE_ERRORS_ENABLED === false) {
            return false;
        }

        $environment = function_exists('wp_get_environment_type')
            ? (string) wp_get_environment_type()
            : (defined('WP_ENVIRONMENT_TYPE') ? (string) WP_ENVIRONMENT_TYPE : 'production');

        $explicit = defined('WP_AWARE_ERRORS_ENABLED') && WP_AWARE_ERRORS_ENABLED === true;

        if ($environment === 'production') {
            return $explicit
                && defined('WP_AWARE_ERRORS_ALLOW_PRODUCTION')
                && WP_AWARE_ERRORS_ALLOW_PRODUCTION === true;
        }

        if ($explicit) {
            return true;
        }

        if (in_array($environment, ['local', 'development'], true)) {
            return true;
        }

        return defined('WP_DEBUG') && WP_DEBUG === true;
    }
}
