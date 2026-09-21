<?php
/**
 * Optional WordPress fatal error handler drop-in for WP Aware Errors.
 *
 * Copy this file to: wp-content/fatal-error-handler.php
 *
 * WordPress loads this very early. It intentionally contains a tiny standalone
 * renderer so fatal errors can still be identified even when normal plugins do
 * not finish loading.
 */

declare(strict_types=1);

if (! defined('ABSPATH') || ! class_exists('WP_Fatal_Error_Handler')) {
    return null;
}

final class WP_Aware_Errors_Fatal_Handler extends WP_Fatal_Error_Handler
{
    protected function display_default_error_template($error, $handled): void
    {
        if (! $this->should_render()) {
            parent::display_default_error_template($error, $handled);
            return;
        }

        $message = is_array($error) ? (string) ($error['message'] ?? 'Unknown fatal error') : 'Unknown fatal error';
        $file = is_array($error) ? (string) ($error['file'] ?? '') : '';
        $line = is_array($error) ? (int) ($error['line'] ?? 0) : 0;
        $relative = str_replace('\\', '/', $file);
        if (defined('ABSPATH')) {
            $root = str_replace('\\', '/', rtrim((string) ABSPATH, '/\\')) . '/';
            if (str_starts_with($relative, $root)) {
                $relative = substr($relative, strlen($root));
            }
        }

        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $source = $this->classify($file);
        $code = $this->source($file, $line);
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Fatal Error — WP Aware Errors</title><style>body{margin:0;background:#0b0d10;color:#edf2f7;font:15px/1.55 system-ui,sans-serif}.x{max-width:1200px;margin:0 auto;padding:42px}.k{color:#ff5a36;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:800}h1{font-size:20px;margin:8px 0}.m{font-size:30px;line-height:1.15;font-weight:750;letter-spacing:-.025em}.s{display:flex;gap:12px;margin:24px 0}.c{flex:1;border:1px solid #252b33;background:#11151a;border-radius:12px;padding:15px}.c span{display:block;color:#8e99a8;font-size:11px;text-transform:uppercase}.code{border:1px solid #252b33;background:#11151a;border-radius:12px;padding:12px 0;font:13px/1.65 ui-monospace,monospace;overflow:auto}.l{display:grid;grid-template-columns:60px minmax(700px,1fr);padding:0 16px}.l b{color:#5e6976;text-align:right;padding-right:16px}.l.hot{background:rgba(255,90,54,.12);box-shadow:inset 3px 0 #ff5a36}.l.hot b{color:#ff5a36}.n{color:#7e8996;margin-top:20px}</style>';
        echo '<main class="x"><div class="k">WP Aware Errors · early fatal handler</div><h1>PHP Fatal Error</h1><div class="m">' . $e($message) . '</div>';
        echo '<div class="s"><div class="c"><span>Likely source</span><strong>' . $e($source) . '</strong></div><div class="c"><span>Location</span><strong>' . $e($relative . ':' . $line) . '</strong></div></div>';
        echo '<div class="code">' . $code . '</div><div class="n">This is the early WordPress fatal-error-handler drop-in. The full plugin screen may not be available because WordPress did not finish loading.</div></main>';
    }

    private function should_render(): bool
    {
        if (defined('WP_AWARE_ERRORS_ENABLED') && WP_AWARE_ERRORS_ENABLED === false) {
            return false;
        }

        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : (defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production');
        $explicit = defined('WP_AWARE_ERRORS_ENABLED') && WP_AWARE_ERRORS_ENABLED === true;

        if ($env === 'production') {
            return $explicit && defined('WP_AWARE_ERRORS_ALLOW_PRODUCTION') && WP_AWARE_ERRORS_ALLOW_PRODUCTION === true;
        }

        return $explicit || in_array($env, ['local', 'development'], true) || (defined('WP_DEBUG') && WP_DEBUG === true);
    }

    private function classify(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $pluginDir = defined('WP_PLUGIN_DIR') ? str_replace('\\', '/', (string) WP_PLUGIN_DIR) : '';
        $contentDir = defined('WP_CONTENT_DIR') ? str_replace('\\', '/', (string) WP_CONTENT_DIR) : '';

        if ($pluginDir !== '' && str_starts_with($file, rtrim($pluginDir, '/') . '/')) {
            $relative = ltrim(substr($file, strlen(rtrim($pluginDir, '/'))), '/');
            return 'Plugin: ' . (explode('/', $relative)[0] ?? 'unknown');
        }
        if ($contentDir !== '' && str_starts_with($file, rtrim($contentDir, '/') . '/themes/')) {
            $relative = ltrim(substr($file, strlen(rtrim($contentDir, '/') . '/themes')), '/');
            return 'Theme: ' . (explode('/', $relative)[0] ?? 'unknown');
        }
        if (str_contains($file, '/wp-includes/') || str_contains($file, '/wp-admin/')) {
            return 'WordPress Core';
        }
        if (str_contains($file, '/vendor/')) {
            return 'Composer vendor';
        }
        return 'Application';
    }

    private function source(string $file, int $line): string
    {
        if ($file === '' || ! is_readable($file) || $line < 1) {
            return '<div class="l">Source file unavailable.</div>';
        }

        $rows = @file($file, FILE_IGNORE_NEW_LINES);
        if (! is_array($rows)) {
            return '<div class="l">Source file unavailable.</div>';
        }

        $start = max(1, $line - 8);
        $end = min(count($rows), $line + 8);
        $html = '';
        for ($i = $start; $i <= $end; $i++) {
            $text = htmlspecialchars((string) ($rows[$i - 1] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<div class="l' . ($i === $line ? ' hot' : '') . '"><b>' . $i . '</b><code>' . $text . '</code></div>';
        }
        return $html;
    }
}

return new WP_Aware_Errors_Fatal_Handler();
