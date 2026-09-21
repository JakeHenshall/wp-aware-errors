<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class PluginThemeIndex
{
    /** @var array<string,array<string,mixed>> */
    private static array $plugins = [];
    /** @var array<string,array<string,mixed>> */
    private static array $themes = [];
    private static bool $primed = false;

    public static function primeLater(): void
    {
        if (function_exists('add_action')) {
            add_action('plugins_loaded', [self::class, 'prime'], PHP_INT_MAX);
            add_action('after_setup_theme', [self::class, 'primeThemes'], PHP_INT_MAX);
        }
    }

    public static function prime(): void
    {
        if (self::$primed || ! defined('WP_PLUGIN_DIR')) {
            return;
        }
        self::$primed = true;

        if (! function_exists('get_plugins') && defined('ABSPATH')) {
            $pluginFile = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_file($pluginFile)) {
                require_once $pluginFile;
            }
        }
        if (! function_exists('get_plugins')) {
            return;
        }

        $active = function_exists('get_option') ? (array) get_option('active_plugins', []) : [];
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        $active = array_values(array_unique(array_map('strval', $active)));

        foreach (get_plugins() as $file => $data) {
            $file = (string) $file;
            $slug = self::slugFromPluginFile($file);
            $mainFile = rtrim((string) WP_PLUGIN_DIR, '/\\') . '/' . $file;
            $extra = self::extraHeaders($mainFile);
            self::$plugins[$slug] = [
                'slug' => $slug,
                'file' => $file,
                'main_file' => $mainFile,
                'name' => (string) ($data['Name'] ?? $slug),
                'version' => (string) ($data['Version'] ?? ''),
                'requires_wp' => (string) ($data['RequiresWP'] ?? ''),
                'requires_php' => (string) ($data['RequiresPHP'] ?? ''),
                'requires_plugins' => self::parseSlugs((string) ($data['RequiresPlugins'] ?? '')),
                'active' => in_array($file, $active, true),
                'wc_requires' => (string) ($extra['WCRequires'] ?? ''),
                'wc_tested' => (string) ($extra['WCTested'] ?? ''),
            ];
        }
    }

    public static function primeThemes(): void
    {
        if (! function_exists('wp_get_theme')) {
            return;
        }
        $theme = wp_get_theme();
        if ($theme->exists()) {
            self::$themes[(string) $theme->get_stylesheet()] = [
                'name' => (string) $theme->get('Name'),
                'version' => (string) $theme->get('Version'),
            ];
        }
        $parent = $theme->parent();
        if ($parent && $parent->exists()) {
            self::$themes[(string) $parent->get_stylesheet()] = [
                'name' => (string) $parent->get('Name'),
                'version' => (string) $parent->get('Version'),
            ];
        }
    }

    /** @return array<string,mixed>|null */
    public static function plugin(string $slug): ?array
    {
        if (! self::$primed) self::prime();
        return self::$plugins[$slug] ?? null;
    }

    /** @return array<string,mixed>|null */
    public static function pluginByFile(string $file): ?array
    {
        if (! self::$primed) self::prime();
        foreach (self::$plugins as $plugin) {
            if (($plugin['file'] ?? '') === $file) return $plugin;
        }
        return null;
    }

    /** @return array<string,array<string,mixed>> */
    public static function plugins(): array
    {
        if (! self::$primed) self::prime();
        return self::$plugins;
    }

    /** @return array<string,mixed>|null */
    public static function theme(string $slug): ?array
    {
        if (self::$themes === []) self::primeThemes();
        return self::$themes[$slug] ?? null;
    }

    private static function slugFromPluginFile(string $file): string
    {
        if (str_contains($file, '/')) return explode('/', $file)[0];
        return basename($file, '.php');
    }

    /** @return list<string> */
    private static function parseSlugs(string $value): array
    {
        if ($value === '') return [];
        return array_values(array_filter(array_map(
            static fn(string $v): string => trim($v),
            explode(',', $value)
        )));
    }

    /** @return array<string,string> */
    private static function extraHeaders(string $file): array
    {
        if (! is_readable($file)) return [];
        if (function_exists('get_file_data')) {
            $data = get_file_data($file, [
                'WCRequires' => 'WC requires at least',
                'WCTested' => 'WC tested up to',
            ], 'plugin');
            return is_array($data) ? array_map('strval', $data) : [];
        }
        $head = (string) @file_get_contents($file, false, null, 0, 8192);
        $out = [];
        if (preg_match('/^[ \t\/*#@]*WC requires at least:\s*(.+)$/mi', $head, $m)) $out['WCRequires'] = trim($m[1]);
        if (preg_match('/^[ \t\/*#@]*WC tested up to:\s*(.+)$/mi', $head, $m)) $out['WCTested'] = trim($m[1]);
        return $out;
    }
}
