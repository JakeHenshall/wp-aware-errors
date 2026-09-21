<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class Installation
{
    public function __construct(
        public string $mode,
        public string $entryFile,
        public string $root,
        public string $label
    ) {}

    public static function detect(string $entryFile): self
    {
        $entry = self::normalize($entryFile);
        $root = self::normalize(dirname($entryFile));

        $mu = defined('WPMU_PLUGIN_DIR') ? self::normalize((string) WPMU_PLUGIN_DIR) : '';
        $plugins = defined('WP_PLUGIN_DIR') ? self::normalize((string) WP_PLUGIN_DIR) : '';
        $themes = function_exists('get_theme_root') ? self::normalize((string) get_theme_root()) : (defined('WP_CONTENT_DIR') ? self::normalize((string) WP_CONTENT_DIR . '/themes') : '');

        if ($mu !== '' && self::inside($entry, $mu)) {
            return new self('mu-plugin', $entryFile, dirname($entryFile), 'MU Plugin');
        }
        if ($plugins !== '' && self::inside($entry, $plugins)) {
            return new self('plugin', $entryFile, dirname($entryFile), 'Plugin');
        }
        if ($themes !== '' && self::inside($entry, $themes)) {
            return new self('theme', $entryFile, dirname($entryFile), 'Theme Drop-in');
        }

        return new self('standalone', $entryFile, dirname($entryFile), 'Standalone');
    }

    public function isPlugin(): bool { return $this->mode === 'plugin'; }
    public function isTheme(): bool { return $this->mode === 'theme'; }
    public function isMuPlugin(): bool { return $this->mode === 'mu-plugin'; }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }

    private static function inside(string $file, string $directory): bool
    {
        $directory = self::normalize($directory);
        return $directory !== '' && ($file === $directory || str_starts_with($file, $directory . '/'));
    }
}
