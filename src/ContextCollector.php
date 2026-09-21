<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class ContextCollector
{
    /** @return array<string,mixed> */
    public static function collect(ExceptionData $error): array
    {
        $likely = self::likelySource($error);
        $hookNames = array_merge(HookRecorder::activeStack(), HookRecorder::recentNames(6));
        $memoryFatal = $error->isMemoryExhausted();
        return [
            'installation' => self::installation(),
            'environment' => self::environment(),
            'request' => self::request(),
            'hooks' => [
                'active' => HookRecorder::activeStack(),
                'recent' => HookRecorder::recentDetailed(),
                'ownership' => $memoryFatal ? [] : ComponentOwnership::callbacksForHooks($hookNames),
            ],
            'database' => self::database(),
            'woocommerce' => WooCommerceContext::collect(),
            'likely_source' => $likely,
            'compatibility' => CompatibilityInspector::inspect($likely),
        ];
    }

    /** @return array<string,mixed> */
    private static function installation(): array
    {
        $i = Runtime::installation();
        return [
            'mode' => $i->mode,
            'label' => $i->label,
            'root' => FrameClassifier::relative($i->root),
            'version' => defined('WP_AWARE_ERRORS_VERSION') ? WP_AWARE_ERRORS_VERSION : 'dev',
        ];
    }

    /** @return array<string,mixed> */
    private static function environment(): array
    {
        global $wp_version;
        $theme = null;
        if (function_exists('wp_get_theme')) {
            $wpTheme = wp_get_theme();
            if ($wpTheme->exists()) {
                $theme = [
                    'name' => (string) $wpTheme->get('Name'),
                    'version' => (string) $wpTheme->get('Version'),
                    'stylesheet' => (string) $wpTheme->get_stylesheet(),
                ];
            }
        }
        return [
            'wordpress' => isset($wp_version) ? (string) $wp_version : 'unknown',
            'php' => PHP_VERSION,
            'environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : (defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production'),
            'development_mode' => function_exists('wp_get_development_mode') ? wp_get_development_mode() : '',
            'multisite' => function_exists('is_multisite') ? is_multisite() : false,
            'memory_limit' => ini_get('memory_limit') ?: 'unknown',
            'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : null,
            'debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'savequeries' => defined('SAVEQUERIES') ? SAVEQUERIES : false,
            'theme' => $theme,
        ];
    }

    /** @return array<string,mixed> */
    private static function request(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = Sanitizer::value($name, $value);
            }
        }
        $runtime = Runtime::requestContext();
        return [
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'uri' => Sanitizer::value('uri', $_SERVER['REQUEST_URI'] ?? ''),
            'query' => Sanitizer::value('query', $_GET),
            'post' => Sanitizer::value('post', $_POST),
            'headers' => $headers,
            'ajax' => function_exists('wp_doing_ajax') ? wp_doing_ajax() : (defined('DOING_AJAX') && DOING_AJAX),
            'ajax_action' => (defined('DOING_AJAX') && DOING_AJAX) ? Sanitizer::value('action', $_REQUEST['action'] ?? '') : '',
            'admin_post_action' => isset($_REQUEST['action']) && str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), 'admin-post.php') ? Sanitizer::value('action', $_REQUEST['action']) : '',
            'cron' => function_exists('wp_doing_cron') ? wp_doing_cron() : (defined('DOING_CRON') && DOING_CRON),
            'rest' => defined('REST_REQUEST') && REST_REQUEST,
            'rest_route' => RequestInspector::restRoute(),
            'rest_method' => (string) ($runtime['rest_method'] ?? ''),
            'rest_params' => $runtime['rest_params'] ?? [],
            'cli' => defined('WP_CLI') && WP_CLI,
        ];
    }

    /** @return array<string,mixed> */
    private static function database(): array
    {
        global $wpdb;
        if (! defined('SAVEQUERIES') || ! SAVEQUERIES || ! isset($wpdb) || ! isset($wpdb->queries) || ! is_array($wpdb->queries)) {
            return ['enabled' => false, 'queries' => []];
        }
        $queries = [];
        foreach (array_slice($wpdb->queries, -15) as $entry) {
            if (! is_array($entry)) continue;
            $queries[] = [
                'sql' => Sanitizer::sql((string) ($entry[0] ?? '')),
                'seconds' => isset($entry[1]) ? (float) $entry[1] : null,
                'caller' => (string) ($entry[2] ?? ''),
            ];
        }
        return ['enabled' => true, 'queries' => $queries];
    }

    /** @return array<string,mixed> */
    private static function likelySource(ExceptionData $error): array
    {
        $class = FrameClassifier::classify($error->file);
        if (($class['internal'] ?? false) === false) return $class;
        foreach ($error->trace as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file === '') continue;
            $candidate = FrameClassifier::classify($file);
            if (($candidate['internal'] ?? false) === false) return $candidate;
        }
        return $class;
    }
}
