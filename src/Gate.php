<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class Gate
{
    public static function enabled(): bool
    {
        $enabled = defined('WP_AWARE_ERRORS_ENABLED') ? self::toBool(WP_AWARE_ERRORS_ENABLED) : null;
        $allowProduction = defined('WP_AWARE_ERRORS_ALLOW_PRODUCTION') && self::toBool(WP_AWARE_ERRORS_ALLOW_PRODUCTION);
        $debug = self::debugEnabled();

        return self::decide(self::environment(), $enabled, $allowProduction, $debug);
    }

    /**
     * Returns a normalised WordPress environment type. Unknown or unavailable
     * environment data fails closed to production.
     */
    public static function environment(): string
    {
        $environment = function_exists('wp_get_environment_type')
            ? (string) wp_get_environment_type()
            : (defined('WP_ENVIRONMENT_TYPE') ? (string) WP_ENVIRONMENT_TYPE : 'production');
        $environment = strtolower(trim($environment));

        return in_array($environment, ['local', 'development', 'staging', 'production'], true)
            ? $environment
            : 'production';
    }

    public static function explicitlyEnabled(): bool
    {
        return defined('WP_AWARE_ERRORS_ENABLED') && self::toBool(WP_AWARE_ERRORS_ENABLED);
    }

    /**
     * Whether the plugin may handle errors at all. An explicit false disables
     * the handler; otherwise generic handling remains available even when
     * detailed output is not.
     */
    public static function handlingEnabled(): bool
    {
        return ! (defined('WP_AWARE_ERRORS_ENABLED') && ! self::toBool(WP_AWARE_ERRORS_ENABLED));
    }

    public static function productionAllowed(): bool
    {
        return defined('WP_AWARE_ERRORS_ALLOW_PRODUCTION') && self::toBool(WP_AWARE_ERRORS_ALLOW_PRODUCTION);
    }

    /**
     * Expensive hook/request diagnostics are only useful when detailed output
     * could be authorised. This keeps production/staging generic handling cheap.
     */
    public static function diagnosticsEnabled(): bool
    {
        $environment = self::environment();
        if ($environment === 'production') {
            return self::explicitlyEnabled() && self::productionAllowed();
        }
        if ($environment === 'staging') {
            return self::explicitlyEnabled();
        }

        return in_array($environment, ['local', 'development'], true);
    }

    public static function debugEnabled(): bool
    {
        return defined('WP_DEBUG') && self::toBool(WP_DEBUG);
    }

    /**
     * Pure decision helper so the gate can be tested without defining constants.
     */
    public static function decide(string $environment, ?bool $enabled, bool $allowProduction, bool $debug): bool
    {
        if (in_array($environment, ['local', 'development', 'staging', 'production'], true) === false) {
            $environment = 'production';
        }
        if ($enabled === false) {
            return false;
        }

        if ($environment === 'production') {
            return $enabled === true && $allowProduction;
        }

        if ($enabled === true) {
            return true;
        }

        return in_array($environment, ['local', 'development'], true) || $debug;
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
