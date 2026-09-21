<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

final class HookRecorder
{
    /** @var list<array<string,mixed>> */
    private static array $history = [];
    private static int $limit = 50;
    private static bool $recording = false;
    private static bool $captureArgs = true;
    private static bool $stopped = false;

    /** Hooks whose payloads are routinely huge (options trees, query results, REST bodies). */
    private const FAT_HOOKS = [
        'alloptions' => true,
        'pre_wp_load_alloptions' => true,
        'pre_update_option' => true,
        'pre_update_option_active_plugins' => true,
        'rewrite_rules_array' => true,
        'posts_results' => true,
        'the_posts' => true,
        'found_posts' => true,
        'posts_pre_query' => true,
        'the_content' => true,
        'content_save_pre' => true,
        'content_filtered_save_pre' => true,
        'rest_pre_echo_response' => true,
        'rest_post_dispatch' => true,
        'rest_pre_dispatch' => true,
        'wp_insert_post_data' => true,
        'wp_insert_attachment_data' => true,
        'pre_post_update' => true,
        'attachment_fields_to_save' => true,
        'customize_changeset_save_data' => true,
        'widget_update_callback' => true,
        'pre_update_option_rewrite_rules' => true,
        'pre_set_theme_mod_nav_menu_locations' => true,
    ];

    public static function register(): void
    {
        if (! function_exists('add_filter')) return;
        add_filter('all', [self::class, 'record'], PHP_INT_MIN, 4);
    }

    public static function record(mixed ...$args): mixed
    {
        $first = $args[0] ?? null;
        if (self::$recording || self::$stopped) {
            return $first;
        }

        self::$recording = true;
        try {
            $name = function_exists('current_filter') ? (string) current_filter() : '';
            if ($name === '' || $name === 'all') {
                return $first;
            }

            $pressure = self::memoryPressure();
            if ($pressure >= 2) {
                self::$stopped = true;
                return $first;
            }
            if ($pressure >= 1) {
                self::$captureArgs = false;
            }

            $item = [
                'name' => $name,
                'time' => microtime(true),
                'args' => [],
            ];

            $wantArgs = self::$captureArgs
                && (! defined('WP_AWARE_ERRORS_CAPTURE_HOOK_ARGUMENTS') || WP_AWARE_ERRORS_CAPTURE_HOOK_ARGUMENTS !== false)
                && ! isset(self::FAT_HOOKS[$name]);

            if ($wantArgs) {
                foreach (array_slice($args, 0, 4) as $i => $arg) {
                    $item['args']['arg' . $i] = self::summarize($arg);
                }
                if (count($args) > 4) {
                    $item['args']['…'] = '[additional arguments omitted]';
                }
            }

            self::$history[] = $item;
            $overflow = count(self::$history) - self::$limit;
            if ($overflow > 0) {
                self::$history = array_slice(self::$history, $overflow);
            }
        } catch (Throwable) {
        } finally {
            self::$recording = false;
        }

        return $first;
    }

    /** @return list<string> */
    public static function recentNames(int $limit = 12): array
    {
        $names = array_map(static fn(array $item): string => (string) $item['name'], self::$history);
        return array_values(array_slice($names, -$limit));
    }

    /** @return list<array<string,mixed>> */
    public static function recentDetailed(int $limit = 8): array
    {
        return array_values(array_slice(self::$history, -$limit));
    }

    /** @return list<string> */
    public static function activeStack(): array
    {
        global $wp_current_filter;
        return is_array($wp_current_filter) ? array_values(array_map('strval', $wp_current_filter)) : [];
    }

    private static function summarize(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return strlen($value) > 120 ? substr($value, 0, 120) . '…' : $value;
        }
        if (is_array($value)) {
            $keys = [];
            $n = 0;
            foreach ($value as $key => $_) {
                if ($n++ >= 8) {
                    break;
                }
                $label = (string) $key;
                $keys[] = strlen($label) > 40 ? substr($label, 0, 40) . '…' : $label;
            }
            return [
                '__type' => 'array',
                'count' => count($value),
                'keys' => $keys,
            ];
        }
        if (is_object($value)) {
            $class = $value::class;
            if ($class === 'WP_Post') {
                return ['__class' => $class, 'ID' => (int) ($value->ID ?? 0), 'post_type' => (string) ($value->post_type ?? '')];
            }
            if ($class === 'WP_REST_Request' && method_exists($value, 'get_route')) {
                try {
                    return [
                        '__class' => $class,
                        'route' => (string) $value->get_route(),
                        'method' => method_exists($value, 'get_method') ? (string) $value->get_method() : '',
                    ];
                } catch (Throwable) {
                }
            }
            if (str_contains($class, 'WC_Order') && method_exists($value, 'get_id')) {
                try {
                    return ['__class' => $class, 'id' => (int) $value->get_id()];
                } catch (Throwable) {
                }
            }
            return ['__class' => $class];
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        return gettype($value);
    }

    /** 0 = ok, 1 = drop argument snapshots, 2 = stop recording. */
    private static function memoryPressure(): int
    {
        $limit = self::memoryLimitBytes();
        if ($limit <= 0) {
            return 0;
        }
        $used = memory_get_usage(true);
        $free = $limit - $used;
        if ($used >= (int) ($limit * 0.80) || $free < 8 * 1024 * 1024) {
            return 2;
        }
        if ($used >= (int) ($limit * 0.60)) {
            return 1;
        }
        return 0;
    }

    private static function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $unit = strtolower(substr($raw, -1));
        $n = (float) $raw;
        return (int) match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => (int) $raw,
        };
    }
}
