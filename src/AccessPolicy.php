<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

/**
 * Central access policy for detailed error information.
 *
 * Rules:
 * - Detailed pages are for authenticated administrators by default.
 * - Local/development administrators keep the convenient default.
 * - Staging administrators require an explicit WP_AWARE_ERRORS_ENABLED opt-in.
 * - Production administrators require both explicit enablement and production opt-in.
 * - Anonymous visitors never get details on staging/production.
 * - Anonymous local/development debugging requires WP_AWARE_ERRORS_ALLOW_ANONYMOUS=true.
 * - WP_DEBUG alone never grants detailed access.
 * - Any failure or unknown state fails closed.
 */
final class AccessPolicy
{
    public static function environment(): string
    {
        try {
            return Gate::environment();
        } catch (\Throwable) {
            return 'production';
        }
    }

    public static function isAuthorizedAdmin(): bool
    {
        try {
            if (! function_exists('is_user_logged_in') || ! is_user_logged_in()) {
                return false;
            }
            if (! function_exists('current_user_can')) {
                return false;
            }

            $capability = function_exists('apply_filters')
                ? apply_filters('wp_aware_errors_view_capability', 'manage_options')
                : 'manage_options';
            if (! is_string($capability) || $capability === '') {
                return false;
            }

            return (bool) current_user_can($capability);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function anonymousDebuggingEnabled(): bool
    {
        $environment = self::environment();
        if (! in_array($environment, ['local', 'development'], true)) {
            return false;
        }
        if (! defined('WP_AWARE_ERRORS_ALLOW_ANONYMOUS')) {
            return false;
        }

        return Gate::toBool(WP_AWARE_ERRORS_ALLOW_ANONYMOUS);
    }

    public static function canViewDetails(): bool
    {
        try {
            return self::decide(
                self::environment(),
                self::isAuthorizedAdmin(),
                Gate::explicitlyEnabled(),
                Gate::productionAllowed(),
                self::anonymousDebuggingEnabled(),
                Gate::enabled()
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Pure policy decision used by unit tests.
     */
    public static function decide(
        string $environment,
        bool $authorizedAdmin,
        bool $explicitlyEnabled,
        bool $allowProduction,
        bool $anonymousOptIn,
        bool $gateEnabled
    ): bool {
        if (! $gateEnabled) {
            return false;
        }

        $environment = in_array($environment, ['local', 'development', 'staging', 'production'], true)
            ? $environment
            : 'production';

        if ($authorizedAdmin) {
            if ($environment === 'production') {
                return $explicitlyEnabled && $allowProduction;
            }
            if ($environment === 'staging') {
                return $explicitlyEnabled;
            }

            return in_array($environment, ['local', 'development'], true);
        }

        if (! in_array($environment, ['local', 'development'], true)) {
            return false;
        }

        return $anonymousOptIn;
    }
}
