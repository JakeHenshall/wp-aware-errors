<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

/**
 * Clean public error page. It must never contain exception details, request
 * data, file paths, stack traces or diagnostic context.
 */
final class GenericErrorPage
{
    public static function render(string $reference): void
    {
        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo self::html($reference);
    }

    public static function html(string $reference): string
    {
        $reference = ErrorReference::isValid($reference) ? $reference : ErrorReference::generate();
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $language = function_exists('get_bloginfo') ? trim((string) get_bloginfo('language')) : '';
        if ($language === '') {
            $language = 'en';
        }
        $direction = function_exists('is_rtl') && is_rtl() ? ' dir="rtl"' : '';
        $class = '';
        try {
            $class = Settings::htmlClass();
        } catch (\Throwable) {
        }

        return '<!doctype html><html lang="' . $e($language) . '"' . $direction . $class . '>'
            . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>Something went wrong</title>'
            . '<style>'
            . ':root{color-scheme:dark}*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:#0b0d10;color:#eef2f7;font:15px/1.55 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}'
            . 'main{min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:32px 20px}'
            . '.card{width:min(620px,100%);padding:32px;border:1px solid #252b33;border-radius:14px;background:#11151a}'
            . '.eyebrow{color:#ff5a36;text-transform:uppercase;letter-spacing:.14em;font-size:11px;font-weight:800}'
            . 'h1{font-size:clamp(24px,5vw,34px);line-height:1.15;margin:8px 0 12px}p{margin:0 0 20px;color:#c8d0da}'
            . 'code{display:inline-block;padding:6px 9px;border:1px solid #303842;border-radius:7px;background:#171c22;color:#fff;font:13px ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}'
            . 'a{color:#ff8a72}'
            . 'html.theme-light{color-scheme:light}html.theme-light,html.theme-light body{background:#f6f4f1;color:#1d1a17}'
            . 'html.theme-light .card{background:#fff;border-color:#e6e0d8}html.theme-light p{color:#3f3a36}'
            . 'html.theme-light code{background:#faf8f6;border-color:#e6e0d8;color:#1d1a17}html.theme-light .eyebrow,html.theme-light a{color:#c2410c}'
            . '</style></head><body><main><div class="card">'
            . '<div class="eyebrow">WP Aware Errors</div>'
            . '<h1>Something went wrong</h1>'
            . '<p>The website encountered an unexpected error.</p>'
            . '<p>Reference: <code>' . $e($reference) . '</code></p>'
            . '</div></main></body></html>';
    }
}
