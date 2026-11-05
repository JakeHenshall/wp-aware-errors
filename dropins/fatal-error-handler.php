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
        $disabled = defined('WP_AWARE_ERRORS_ENABLED') && ! $this->flag(WP_AWARE_ERRORS_ENABLED);
        if ($disabled) {
            parent::display_default_error_template($error, $handled);
            return;
        }

        // Tell the full plugin (when active) that this fatal has already been rendered.
        if (! defined('WP_AWARE_ERRORS_FATAL_RENDERED')) {
            define('WP_AWARE_ERRORS_FATAL_RENDERED', true);
        }

        $reference = $this->reference();

        if (! $this->canShowDetails()) {
            $this->displayGeneric($reference);
            return;
        }

        $this->storeEarlyReference($reference);
        if (function_exists('error_log')) {
            error_log('WP Aware Errors fatal reference: ' . $reference);
        }

        $message = $this->redact(is_array($error) ? (string) ($error['message'] ?? 'Unknown fatal error') : 'Unknown fatal error');
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
        $code = $this->sourceEnabled()
            ? $this->source($file, $line)
            : '<div class="l">Source display is disabled. Define WP_AWARE_ERRORS_SHOW_SOURCE=true to enable it for authorised administrators.</div>';
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $light = function_exists('get_option') && get_option('wp_aware_errors_theme', 'dark') === 'light';
        $css = $light
            ? 'html,body{margin:0;padding:0;width:100%;min-height:100vh;min-height:100dvh;background:#f6f4f1}body{color:#1d1a17;font:15px/1.55 system-ui,sans-serif}.x{width:100%;max-width:none;min-height:100vh;min-height:100dvh;margin:0;padding:32px 40px;display:flex;flex-direction:column}.k{color:#c2410c;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:800}h1{font-size:20px;margin:8px 0}.m{font-size:30px;line-height:1.15;font-weight:750;letter-spacing:-.025em}.s{display:flex;gap:12px;margin:24px 0}.c{flex:1;border:1px solid #e6e0d8;background:#fff;border-radius:12px;padding:15px}.c span{display:block;color:#6f6862;font-size:11px;text-transform:uppercase}.code{border:1px solid #e6e0d8;background:#fff;border-radius:12px;padding:12px 0;font:13px/1.65 ui-monospace,monospace;overflow:auto}.l{display:grid;grid-template-columns:60px minmax(0,1fr);padding:0 16px}.l b{color:#6f6862;text-align:right;padding-right:16px}.l.hot{background:rgba(194,65,12,.1);box-shadow:inset 3px 0 #c2410c}.l.hot b{color:#c2410c}.n{color:#6f6862;margin-top:auto;padding-top:20px}'
            : 'html,body{margin:0;padding:0;width:100%;min-height:100vh;min-height:100dvh;background:#0b0d10}body{color:#edf2f7;font:15px/1.55 system-ui,sans-serif}.x{width:100%;max-width:none;min-height:100vh;min-height:100dvh;margin:0;padding:32px 40px;display:flex;flex-direction:column}.k{color:#ff5a36;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:800}h1{font-size:20px;margin:8px 0}.m{font-size:30px;line-height:1.15;font-weight:750;letter-spacing:-.025em}.s{display:flex;gap:12px;margin:24px 0}.c{flex:1;border:1px solid #252b33;background:#11151a;border-radius:12px;padding:15px}.c span{display:block;color:#8e99a8;font-size:11px;text-transform:uppercase}.code{border:1px solid #252b33;background:#11151a;border-radius:12px;padding:12px 0;font:13px/1.65 ui-monospace,monospace;overflow:auto}.l{display:grid;grid-template-columns:60px minmax(0,1fr);padding:0 16px}.l b{color:#7e8996;text-align:right;padding-right:16px}.l.hot{background:rgba(255,90,54,.12);box-shadow:inset 3px 0 #ff5a36}.l.hot b{color:#ff5a36}.n{color:#7e8996;margin-top:auto;padding-top:20px}';
        $language = function_exists('get_bloginfo') ? trim((string) get_bloginfo('language')) : '';
        if ($language === '') {
            $language = 'en';
        }
        $direction = function_exists('is_rtl') && is_rtl() ? ' dir="rtl"' : '';

        echo '<!doctype html><html lang="' . $e($language) . '"' . $direction . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Fatal Error — WP Aware Errors</title><style>' . $css . '</style></head><body>';
        echo '<main class="x"><div class="k">WP Aware Errors · early fatal handler</div><h1>PHP Fatal Error</h1><p class="m">' . $e($message) . '</p>';
        echo '<div class="s"><div class="c"><span>Likely source</span><strong>' . $e($source) . '</strong></div><div class="c"><span>Location</span><strong>' . $e($relative . ':' . $line) . '</strong></div></div>';
        echo '<div class="code">' . $code . '</div><div class="n">This is the early WordPress fatal-error-handler drop-in. The full plugin screen may not be available because WordPress did not finish loading.<br>Reference: <code>' . $e($reference) . '</code></div></main></body></html>';
    }

    private function displayGeneric(string $reference = ''): void
    {
        if ($reference === '') {
            $reference = $this->reference();
        }

        $this->storeEarlyReference($reference);
        if (function_exists('error_log')) {
            error_log('WP Aware Errors fatal reference: ' . $reference);
        }

        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $language = function_exists('get_bloginfo') ? trim((string) get_bloginfo('language')) : '';
        if ($language === '') {
            $language = 'en';
        }

        echo '<!doctype html><html lang="' . $e($language) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Something went wrong</title>';
        echo '<style>:root{color-scheme:dark}html,body{margin:0;min-height:100%;background:#0b0d10;color:#eef2f7;font:15px/1.55 system-ui,sans-serif}main{min-height:100vh;display:grid;place-items:center;padding:32px 20px}.card{width:min(620px,100%);padding:32px;border:1px solid #252b33;border-radius:14px;background:#11151a}.k{color:#ff5a36;text-transform:uppercase;letter-spacing:.14em;font-size:11px;font-weight:800}h1{font-size:clamp(24px,5vw,34px);line-height:1.15;margin:8px 0 12px}p{margin:0 0 20px;color:#c8d0da}code{display:inline-block;padding:6px 9px;border:1px solid #303842;border-radius:7px;background:#171c22;color:#fff;font:13px ui-monospace,monospace}</style></head><body><main><div class="card"><div class="k">WP Aware Errors</div><h1>Something went wrong</h1><p>The website encountered an unexpected error.</p><p>Reference: <code>' . $e($reference) . '</code></p></div></main></body></html>';
    }

    private function canShowDetails(): bool
    {
        try {
            if (class_exists('Hensh\\WpAwareErrors\\AccessPolicy')) {
                return \Hensh\WpAwareErrors\AccessPolicy::canViewDetails();
            }
        } catch (\Throwable) {
        }

        try {
            $env = $this->environment();
            if ($this->isAuthorizedAdmin()) {
                if ($env === 'production') {
                    return $this->explicitEnabled() && $this->allowProduction();
                }
                if ($env === 'staging') {
                    return $this->explicitEnabled();
                }

                return in_array($env, ['local', 'development'], true);
            }

            return in_array($env, ['local', 'development'], true) && $this->anonymousAllowed();
        } catch (\Throwable) {
            return false;
        }
    }

    private function isAuthorizedAdmin(): bool
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

    private function sourceEnabled(): bool
    {
        return defined('WP_AWARE_ERRORS_SHOW_SOURCE') && $this->flag(WP_AWARE_ERRORS_SHOW_SOURCE);
    }

    /**
     * The drop-in cannot assume the full Sanitizer is loaded. Apply the same
     * credential redaction locally before rendering a fatal message.
     */
    private function redact(string $value): string
    {
        if (strlen($value) > 2000) {
            $value = substr($value, 0, 2000) . '…';
        }

        $patterns = [
            ['/((?:user_pass|password|passwords?|passcodes?|passphrases?|passwd|pass|pwd|secrets?|tokens?|api[_-]?keys?|client[_-]?secrets?|private[_-]?keys?|authorization|authentication|auth|cookies?|wpnonce|nonces?|security|sessions?|card_number|cvv|otp|pin)["\']?\s*[=:]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s&,;]+)/i', '$1••••'],
            ['/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/]+=*/i', '$1 ••••'],
            ['/\b(?:sk|pk|rk)[-_](?:live|test)[-_][A-Za-z0-9_-]{4,}\b/i', '••••'],
            ['/\bsk-[A-Za-z0-9_-]{8,}\b/i', '••••'],
        ];

        foreach ($patterns as [$pattern, $replacement]) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }

    /**
     * Persist only the opaque reference and timestamp. This does not depend on
     * the full plugin being loaded and never stores exception text or paths.
     */
    private function storeEarlyReference(string $reference): void
    {
        try {
            if (class_exists('Hensh\\WpAwareErrors\\ErrorHistory')) {
                \Hensh\WpAwareErrors\ErrorHistory::addEarlyReference($reference);
                return;
            }

            if (! function_exists('get_option') || ! function_exists('update_option')) {
                return;
            }

            $references = get_option('wp_aware_errors_early_fatal_references', []);
            if (! is_array($references)) {
                $references = [];
            }
            $references[] = [
                'reference' => $reference,
                'time' => gmdate('c'),
            ];
            update_option('wp_aware_errors_early_fatal_references', array_slice($references, -10), false);
        } catch (\Throwable) {
        }
    }

    private function anonymousAllowed(): bool
    {
        $env = $this->environment();
        if (! in_array($env, ['local', 'development'], true)) {
            return false;
        }

        return defined('WP_AWARE_ERRORS_ALLOW_ANONYMOUS') && $this->flag(WP_AWARE_ERRORS_ALLOW_ANONYMOUS);
    }

    private function explicitEnabled(): bool
    {
        return defined('WP_AWARE_ERRORS_ENABLED') && $this->flag(WP_AWARE_ERRORS_ENABLED);
    }

    private function allowProduction(): bool
    {
        return defined('WP_AWARE_ERRORS_ALLOW_PRODUCTION') && $this->flag(WP_AWARE_ERRORS_ALLOW_PRODUCTION);
    }

    private function environment(): string
    {
        $env = function_exists('wp_get_environment_type')
            ? (string) wp_get_environment_type()
            : (defined('WP_ENVIRONMENT_TYPE') ? (string) WP_ENVIRONMENT_TYPE : 'production');
        $env = strtolower(trim($env));

        return in_array($env, ['local', 'development', 'staging', 'production'], true)
            ? $env
            : 'production';
    }

    private function reference(): string
    {
        try {
            return 'WAE-' . strtoupper(bin2hex(random_bytes(8)));
        } catch (\Throwable) {
            return 'WAE-' . strtoupper(substr(hash('sha256', uniqid('', true) . microtime(true)), 0, 16));
        }
    }

    private function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }

    private function classify(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $pluginDir = defined('WP_PLUGIN_DIR') ? str_replace('\\', '/', (string) WP_PLUGIN_DIR) : '';
        $muDir = defined('WPMU_PLUGIN_DIR') ? str_replace('\\', '/', (string) WPMU_PLUGIN_DIR) : '';
        $contentDir = defined('WP_CONTENT_DIR') ? str_replace('\\', '/', (string) WP_CONTENT_DIR) : '';

        if ($muDir !== '' && str_starts_with($file, rtrim($muDir, '/') . '/')) {
            $relative = ltrim(substr($file, strlen(rtrim($muDir, '/'))), '/');
            return 'MU Plugin: ' . (explode('/', $relative)[0] ?? 'unknown');
        }
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
        $size = @filesize($file);
        if ($size !== false && $size > 2 * 1024 * 1024) {
            return '<div class="l">Source file is too large to display.</div>';
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
