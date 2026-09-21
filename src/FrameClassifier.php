<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class FrameClassifier
{
    /** @return array<string,mixed> */
    public static function classify(string $file): array
    {
        $normalized = self::normalize($file);
        if ($normalized === '') return ['kind' => 'runtime', 'label' => 'Runtime', 'internal' => true];

        // MU plugins must be checked before normal plugins because custom directory layouts can overlap.
        if (defined('WPMU_PLUGIN_DIR') && self::inside($normalized, (string) WPMU_PLUGIN_DIR)) {
            $relative = ltrim(substr($normalized, strlen(self::normalize((string) WPMU_PLUGIN_DIR))), '/');
            $slug = explode('/', $relative)[0] ?? 'mu-plugin';
            if (! str_contains($relative, '/')) $slug = basename($relative, '.php');
            return ['kind' => 'mu-plugin', 'label' => 'MU Plugin: ' . $slug, 'slug' => $slug, 'internal' => false];
        }

        if (defined('WP_PLUGIN_DIR') && self::inside($normalized, (string) WP_PLUGIN_DIR)) {
            $relative = ltrim(substr($normalized, strlen(self::normalize((string) WP_PLUGIN_DIR))), '/');
            $slug = explode('/', $relative)[0] ?? 'plugin';
            if (! str_contains($relative, '/')) $slug = basename($relative, '.php');
            $meta = PluginThemeIndex::plugin($slug);
            $label = $meta ? (string) $meta['name'] . (($meta['version'] ?? '') !== '' ? ' ' . $meta['version'] : '') : $slug;
            return ['kind' => 'plugin', 'label' => $label, 'slug' => $slug, 'internal' => false, 'meta' => $meta];
        }

        $themes = function_exists('get_theme_root') ? self::normalize((string) get_theme_root()) : (defined('WP_CONTENT_DIR') ? self::normalize((string) WP_CONTENT_DIR . '/themes') : '');
        if ($themes !== '') {
            if (self::inside($normalized, $themes)) {
                $relative = ltrim(substr($normalized, strlen($themes)), '/');
                $slug = explode('/', $relative)[0] ?? 'theme';
                $meta = PluginThemeIndex::theme($slug);
                $label = $meta ? (string) $meta['name'] . (($meta['version'] ?? '') !== '' ? ' ' . $meta['version'] : '') : $slug;
                return ['kind' => 'theme', 'label' => $label, 'slug' => $slug, 'internal' => false, 'meta' => $meta];
            }
        }

        // Vendor before core so wp-content/vendor or core Composer frames remain distinct.
        if (str_contains($normalized, '/vendor/')) return ['kind' => 'vendor', 'label' => 'Composer vendor', 'internal' => true];
        if (defined('ABSPATH') && self::inside($normalized, (string) ABSPATH)) return ['kind' => 'core', 'label' => 'WordPress Core', 'internal' => true];
        return ['kind' => 'app', 'label' => 'Application', 'internal' => false];
    }

    public static function relative(string $file): string
    {
        $normalized = self::normalize($file);
        if (defined('ABSPATH') && self::inside($normalized, (string) ABSPATH)) {
            return ltrim(substr($normalized, strlen(self::normalize((string) ABSPATH))), '/');
        }
        return $normalized;
    }

    public static function normalize(string $path): string { return str_replace('\\', '/', rtrim($path, '/\\')); }
    private static function inside(string $file, string $directory): bool
    {
        $directory = self::normalize($directory);
        return $directory !== '' && ($file === $directory || str_starts_with($file, $directory . '/'));
    }
}
