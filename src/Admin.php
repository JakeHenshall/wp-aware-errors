<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class Admin
{
    public static function register(): void
    {
        if (! function_exists('add_action')) return;
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_wp_aware_errors_clear_history', [self::class, 'clearHistory']);
        if (Runtime::installation()->isPlugin()) {
            add_filter('plugin_action_links_' . (function_exists('plugin_basename') ? plugin_basename(WP_AWARE_ERRORS_FILE) : 'wp-aware-errors/wp-aware-errors.php'), [self::class, 'actionLinks']);
        }
    }

    public static function menu(): void
    {
        if (! function_exists('add_management_page')) return;
        add_management_page('WP Aware Errors', 'WP Aware Errors', 'manage_options', 'wp-aware-errors', [self::class, 'page']);
    }

    /** @param array<int,string> $links @return array<int,string> */
    public static function actionLinks(array $links): array
    {
        if (function_exists('admin_url')) array_unshift($links, '<a href="' . esc_url(admin_url('tools.php?page=wp-aware-errors')) . '">History</a>');
        return $links;
    }

    public static function clearHistory(): void
    {
        if (! current_user_can('manage_options')) wp_die('Not allowed.');
        check_admin_referer('wp_aware_errors_clear_history');
        ErrorHistory::clear();
        wp_safe_redirect(admin_url('tools.php?page=wp-aware-errors&cleared=1'));
        exit;
    }

    public static function page(): void
    {
        if (! current_user_can('manage_options')) return;
        $history = ErrorHistory::all();
        $installation = Runtime::installation();
        echo '<div class="wrap"><h1>WP Aware Errors</h1>';
        echo '<p><strong>Running as:</strong> ' . esc_html($installation->label) . ' &nbsp; <strong>Version:</strong> ' . esc_html((string) WP_AWARE_ERRORS_VERSION) . '</p>';
        echo '<p>The history is local to this WordPress database and stores only a compact, sanitised summary of the most recent errors.</p>';
        if ($history === []) {
            echo '<div class="notice notice-info inline"><p>No captured errors yet.</p></div></div>';
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=wp_aware_errors_clear_history'), 'wp_aware_errors_clear_history');
        echo '<p><a class="button" href="' . esc_url($url) . '">Clear history</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Error</th><th>Source</th><th>Location</th><th>Request</th></tr></thead><tbody>';
        foreach ($history as $row) {
            $request = trim((string) ($row['method'] ?? '') . ' ' . (string) ($row['uri'] ?? ''));
            if (! empty($row['ajax_action'])) $request .= ' · AJAX ' . $row['ajax_action'];
            if (! empty($row['rest_route'])) $request .= ' · REST ' . $row['rest_route'];
            echo '<tr><td>' . esc_html((string) ($row['time'] ?? '')) . '</td><td><strong>' . esc_html((string) ($row['type'] ?? '')) . '</strong><br>' . esc_html((string) ($row['message'] ?? '')) . '</td><td>' . esc_html((string) ($row['source'] ?? '')) . '</td><td><code>' . esc_html((string) ($row['file'] ?? '') . ':' . (string) ($row['line'] ?? '')) . '</code></td><td>' . esc_html($request) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
