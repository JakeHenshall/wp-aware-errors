<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class CompatibilityInspector
{
    /** @return array<string,mixed> */
    public static function inspect(array $likelySource): array
    {
        $issues = [];
        $plugin = null;
        if (($likelySource['kind'] ?? '') === 'plugin' && ! empty($likelySource['slug'])) {
            $plugin = PluginThemeIndex::plugin((string) $likelySource['slug']);
            if ($plugin) $issues = array_merge($issues, self::pluginIssues($plugin));
        }

        // Include high-confidence requirement failures across active plugins.
        foreach (PluginThemeIndex::plugins() as $candidate) {
            if (empty($candidate['active'])) continue;
            foreach (self::pluginIssues($candidate) as $issue) {
                if (($issue['severity'] ?? '') === 'error') $issues[] = $issue;
            }
        }

        $dedupe = [];
        foreach ($issues as $issue) {
            $key = ($issue['plugin'] ?? '') . '|' . ($issue['code'] ?? '') . '|' . ($issue['message'] ?? '');
            $dedupe[$key] = $issue;
        }

        return [
            'plugin' => $plugin,
            'issues' => array_values(array_slice($dedupe, 0, 20)),
        ];
    }

    /** @param array<string,mixed> $plugin @return list<array<string,string>> */
    private static function pluginIssues(array $plugin): array
    {
        global $wp_version;
        $issues = [];
        $name = (string) ($plugin['name'] ?? $plugin['slug'] ?? 'Plugin');
        $wp = isset($wp_version) ? (string) $wp_version : '';

        $requiresPhp = (string) ($plugin['requires_php'] ?? '');
        if ($requiresPhp !== '' && version_compare(PHP_VERSION, $requiresPhp, '<')) {
            $issues[] = self::issue($name, 'php_requirement', 'error', "Requires PHP {$requiresPhp}+ but this site is running " . PHP_VERSION . '.');
        }
        $requiresWp = (string) ($plugin['requires_wp'] ?? '');
        if ($requiresWp !== '' && $wp !== '' && version_compare($wp, $requiresWp, '<')) {
            $issues[] = self::issue($name, 'wp_requirement', 'error', "Requires WordPress {$requiresWp}+ but this site is running {$wp}.");
        }

        foreach ((array) ($plugin['requires_plugins'] ?? []) as $depSlug) {
            $dep = PluginThemeIndex::plugin((string) $depSlug);
            if (! $dep) {
                $issues[] = self::issue($name, 'missing_dependency', 'error', "Requires plugin '{$depSlug}', but it is not installed.");
            } elseif (empty($dep['active'])) {
                $issues[] = self::issue($name, 'inactive_dependency', 'error', "Requires plugin '{$dep['name']}', but it is installed and inactive.");
            }
        }

        $wcVersion = WooCommerceContext::version();
        if ($wcVersion !== '') {
            $wcRequires = (string) ($plugin['wc_requires'] ?? '');
            $wcTested = (string) ($plugin['wc_tested'] ?? '');
            if ($wcRequires !== '' && version_compare($wcVersion, $wcRequires, '<')) {
                $issues[] = self::issue($name, 'wc_minimum', 'error', "Requires WooCommerce {$wcRequires}+ but {$wcVersion} is installed.");
            }
            if ($wcTested !== '' && version_compare($wcVersion, $wcTested, '>')) {
                $issues[] = self::issue($name, 'wc_untested', 'warning', "Declares WooCommerce tested up to {$wcTested}; this site is running {$wcVersion}.");
            }
        }

        return $issues;
    }

    /** @return array<string,string> */
    private static function issue(string $plugin, string $code, string $severity, string $message): array
    {
        return compact('plugin', 'code', 'severity', 'message');
    }
}
