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
        add_action('load-tools_page_wp-aware-errors', [self::class, 'maybeRenderPreview']);
        add_action('admin_post_wp_aware_errors_clear_history', [self::class, 'clearHistory']);
        add_action('admin_post_wp_aware_errors_save_settings', [self::class, 'saveSettings']);
        if (Runtime::installation()->isPlugin()) {
            add_filter('plugin_action_links_' . (function_exists('plugin_basename') ? plugin_basename(WP_AWARE_ERRORS_FILE) : 'wp-aware-errors/wp-aware-errors.php'), [self::class, 'actionLinks']);
        }
    }

    public static function menu(): void
    {
        if (! function_exists('add_management_page')) return;
        add_management_page('WP Aware Errors', 'WP Aware Errors', 'manage_options', 'wp-aware-errors', [self::class, 'page']);
        add_management_page('Error screen appearance', 'Error appearance', 'manage_options', 'wp-aware-errors-settings', [self::class, 'settingsPage']);
    }

    /** @param array<int,string> $links @return array<int,string> */
    public static function actionLinks(array $links): array
    {
        if (function_exists('admin_url')) {
            array_unshift($links, '<a href="' . esc_url(admin_url('tools.php?page=wp-aware-errors-settings')) . '">Appearance</a>');
            array_unshift($links, '<a href="' . esc_url(admin_url('tools.php?page=wp-aware-errors')) . '">Previews</a>');
        }
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

    public static function maybeRenderPreview(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $raw = $_GET['preview'] ?? '';
        $slug = is_string($raw) ? sanitize_key(wp_unslash($raw)) : '';
        if ($slug === '' || ! ErrorPreview::has($slug)) {
            return;
        }
        $frame = $_GET['frame'] ?? '';
        $framed = is_string($frame) && wp_unslash($frame) === '1';
        ErrorPreview::send($slug, $framed);
        exit;
    }

    public static function page(): void
    {
        if (! current_user_can('manage_options')) return;
        $history = ErrorHistory::all();
        $installation = Runtime::installation();
        $requestedRaw = $_GET['preview'] ?? '';
        $requested = is_string($requestedRaw) ? sanitize_key(wp_unslash($requestedRaw)) : '';
        echo '<div class="wrap"><h1>WP Aware Errors</h1>';
        echo '<p><strong>Running as:</strong> ' . esc_html($installation->label) . ' &nbsp; <strong>Version:</strong> ' . esc_html((string) WP_AWARE_ERRORS_VERSION) . ' &nbsp; <a href="' . esc_url(admin_url('tools.php?page=wp-aware-errors-settings')) . '">Appearance</a></p>';
        if ($requested !== '' && ! ErrorPreview::has($requested)) {
            echo '<div class="notice notice-warning"><p>That error preview does not exist.</p></div>';
        }
        self::previews();
        echo '<h2>Recent errors</h2>';
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

    private static function previews(): void
    {
        echo '<h2>Error page previews</h2>';
        echo '<p>Each card throws a real PHP error and renders it with <a href="https://github.com/spatie/ignition" target="_blank" rel="noopener noreferrer">Spatie Ignition</a>. The stack and code are from that throw. WordPress context is added beside it. Samples are not saved to the history below.</p>';
        echo '<style>
.wp-aware-previews{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin:16px 0 28px}
.wp-aware-preview{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid #c3c4c7;border-radius:8px;overflow:hidden;box-shadow:0 1px 1px rgba(0,0,0,.04)}
.wp-aware-preview:hover,.wp-aware-preview:focus-within{border-color:#ff5a36;box-shadow:0 0 0 1px #ff5a36}
.wp-aware-preview-shot{height:230px;overflow:hidden;background:#0b0d10;pointer-events:none}.wp-aware-preview-shot.is-light{background:#f6f4f1}
.wp-aware-preview-shot iframe{width:1440px;height:980px;border:0;transform:scale(.22);transform-origin:top left}
@supports (width:1cqw){.wp-aware-preview-shot{container-type:inline-size}.wp-aware-preview-shot iframe{transform:scale(calc(100cqw / 1440px))}}
.wp-aware-preview-body{padding:12px 14px 14px}
.wp-aware-preview-kicker{margin:0 0 4px;color:#ff5a36;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
.wp-aware-preview-body h3{margin:0 0 6px;font-size:15px}
.wp-aware-preview-body p{margin:0;color:#50575e}
.wp-aware-preview-go{display:inline-flex;align-items:center;gap:6px;margin-top:12px;color:#ff5a36;font-size:13px;font-weight:600}
.wp-aware-preview-go:after{content:"\\2192"}
.wp-aware-preview:hover .wp-aware-preview-go,.wp-aware-preview:focus-within .wp-aware-preview-go{color:#e24a28}
.wp-aware-preview > a.wp-aware-preview-link,.wp-aware-preview > a.wp-aware-preview-link:hover,.wp-aware-preview > a.wp-aware-preview-link:focus,.wp-aware-preview > a.wp-aware-preview-link:active{position:absolute;inset:0;z-index:2;overflow:hidden;color:transparent;background:transparent;text-decoration:none;box-shadow:none;outline:none}
.wp-aware-preview:focus-within{outline:2px solid #ff5a36;outline-offset:2px}
</style>';
        echo '<div class="wp-aware-previews">';
        foreach (ErrorPreview::catalog() as $item) {
            $url = add_query_arg('preview', $item['slug'], admin_url('tools.php?page=wp-aware-errors'));
            $frame = add_query_arg('frame', '1', $url);
            echo '<article class="wp-aware-preview">';
            echo '<div class="wp-aware-preview-shot' . (Settings::theme() === 'light' ? ' is-light' : '') . '" aria-hidden="true"><iframe src="' . esc_url($frame) . '" loading="lazy" tabindex="-1" title=""></iframe></div>';
            echo '<div class="wp-aware-preview-body"><p class="wp-aware-preview-kicker">' . esc_html($item['kicker']) . '</p><h3>' . esc_html($item['title']) . '</h3><p>' . esc_html($item['summary']) . '</p><span class="wp-aware-preview-go">Open preview</span></div>';
            echo '<a class="wp-aware-preview-link" target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '"><span class="screen-reader-text">Open preview: ' . esc_html($item['title']) . '</span></a>';
            echo '</article>';
        }
        echo '</div>';
    }

    public static function settingsPage(): void
    {
        if (! current_user_can('manage_options')) return;
        $theme = Settings::theme();
        $preview = admin_url('tools.php?page=wp-aware-errors&preview=white-screen');
        echo '<div class="wrap" style="max-width:680px">';
        echo '<h1>Error screen appearance</h1>';
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Saved. Live errors and previews use this appearance.</p></div>';
        }
        echo '<p>Dark is the default. This sets Spatie Ignition\'s theme for live errors and the previews. It does not change which errors are shown.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wp_aware_errors_save_settings');
        echo '<input type="hidden" name="action" value="wp_aware_errors_save_settings">';
        echo '<style>
.wp-aware-themes{display:flex;gap:12px;margin:16px 0;flex-wrap:wrap}
.wp-aware-themes label{display:flex;gap:12px;align-items:center;flex:1;min-width:220px;margin:0;padding:12px;border:1px solid #c3c4c7;border-radius:8px;background:#fff;cursor:pointer}
.wp-aware-themes label:has(input:checked){border-color:#ff5a36;box-shadow:0 0 0 1px #ff5a36}
.wp-aware-themes small{display:block;color:#646970}
.wp-aware-themes .swatch{width:72px;height:48px;border-radius:6px;flex:none}
.wp-aware-themes .swatch.dark{background:#0b0d10;box-shadow:inset 0 0 0 8px #11151a, inset 0 8px 0 #ff5a36}
.wp-aware-themes .swatch.light{background:#f6f4f1;box-shadow:inset 0 0 0 8px #fff, inset 0 8px 0 #e24a28}
</style>';
        echo '<div class="wp-aware-themes">';
        foreach (['dark' => ['Dark', 'Default'], 'light' => ['Light', 'Same layout, light background']] as $value => $label) {
            echo '<label><input type="radio" name="wp_aware_errors_theme" value="' . esc_attr($value) . '"' . ($theme === $value ? ' checked' : '') . '>';
            echo '<span class="swatch ' . esc_attr($value) . '" aria-hidden="true"></span>';
            echo '<span><strong>' . esc_html($label[0]) . '</strong><small>' . esc_html($label[1]) . '</small></span></label>';
        }
        echo '</div>';
        echo '<p><button class="button button-primary">Save appearance</button> ';
        echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url($preview) . '">Preview white screen</a></p>';
        echo '<p class="description">The preview uses the saved choice. Save first, then open it.</p>';
        echo '</form></div>';
    }

    public static function saveSettings(): void
    {
        if (! current_user_can('manage_options')) wp_die('Not allowed.');
        check_admin_referer('wp_aware_errors_save_settings');
        $raw = $_POST['wp_aware_errors_theme'] ?? 'dark';
        $theme = is_string($raw) ? sanitize_key(wp_unslash($raw)) : 'dark';
        if (! in_array($theme, ['dark', 'light'], true)) $theme = 'dark';
        update_option(Settings::OPTION, $theme, false);
        wp_safe_redirect(admin_url('tools.php?page=wp-aware-errors-settings&updated=1'));
        exit;
    }
}
