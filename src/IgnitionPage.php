<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Hensh\WpAwareErrors\Support\SafeIgnitionReport;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\Ignition\Ignition;
use Throwable;

/**
 * Renders with Spatie Ignition.
 *
 * Ignition::register() is intentionally not used. That handler turns every
 * PHP warning into an exception, which breaks WordPress. This plugin still
 * decides which throwables and fatals reach the page.
 */
final class IgnitionPage
{
    public static function available(): bool
    {
        return class_exists(Ignition::class);
    }

    /** @param array<string,mixed>|null $context */
    public static function render(Throwable $throwable, ?array $context = null, bool $preview = false, string $previewTitle = '', string $backUrl = '', string $reference = ''): void
    {
        // Defence in depth for direct callers. The central ErrorPresenter already
        // applies this policy; previews are admin/nonce-gated by Admin.
        if ($preview && ! AccessPolicy::isAuthorizedAdmin()) {
            GenericErrorPage::render($reference !== '' ? $reference : ErrorReference::generate());
            return;
        }
        if (! $preview && ! AccessPolicy::canViewDetails()) {
            GenericErrorPage::render($reference !== '' ? $reference : ErrorReference::generate());
            return;
        }

        // Only use our sanitising bridge. Ignition's built-in Laravel/PHP solution
        // providers may echo exception text or filesystem paths we cannot audit.
        $solutions = new SolutionProviderRepository([new IgnitionSolutionBridge($context)]);
        $ignition = Ignition::make()
            ->shouldDisplayException(true)
            ->runningInProductionEnvironment(false)
            ->setTheme(Settings::theme() === 'light' ? 'light' : 'dark')
            ->setSolutionProviderRepository($solutions);

        if (defined('ABSPATH')) {
            $ignition->applicationPath((string) ABSPATH);
        }

        // Ignition's own report is never allowed to carry raw request/source data.
        // External reporting is disabled unless a future explicit integration is added.
        $ignition->sendToFlare(null);
        $ignition->configureFlare(static function ($flare): void {
            if (is_object($flare) && method_exists($flare, 'withStackFrameArguments')) {
                $flare->withStackFrameArguments(false);
            }
            if (is_object($flare) && method_exists($flare, 'setApiToken')) {
                $flare->setApiToken('');
            }
        });

        if ($context !== null) {
            $ignition->addCustomHtmlToBody(self::contextPanel($context, $reference));
        }

        if ($preview && $previewTitle !== '') {
            $ignition->addCustomHtmlToBody(self::banner($previewTitle, $backUrl));
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (! headers_sent()) {
            http_response_code($preview ? 200 : 500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        [$safeThrowable, $safeReport] = SafeIgnitionReport::create(
            $throwable,
            $context,
            defined('ABSPATH') ? (string) ABSPATH : ''
        );
        $ignition->renderException($safeThrowable, $safeReport);
    }

    /** @param array<string,mixed> $context */
    private static function contextPanel(array $context, string $reference = ''): string
    {
        $installation = (array) ($context['installation'] ?? []);
        $environment = (array) ($context['environment'] ?? []);
        $request = (array) ($context['request'] ?? []);
        $hooks = (array) ($context['hooks'] ?? []);
        $source = (array) ($context['likely_source'] ?? []);
        $woo = (array) ($context['woocommerce'] ?? []);
        $compat = (array) ($context['compatibility'] ?? []);
        $theme = $environment['theme'] ?? null;
        $themeName = is_array($theme) ? (string) ($theme['name'] ?? '') : '';

        $rows = [
            'Installation' => (string) ($installation['label'] ?? 'Unknown'),
            'WordPress' => (string) ($environment['wordpress'] ?? 'unknown'),
            'PHP' => (string) ($environment['php'] ?? PHP_VERSION),
            'Environment' => (string) ($environment['environment_type'] ?? 'unknown'),
            'Theme' => $themeName,
            'Likely source' => (string) ($source['label'] ?? 'Unknown'),
            'Request' => trim((string) ($request['method'] ?? '') . ' ' . (string) Sanitizer::value('uri', $request['uri'] ?? '')),
            'Hook' => implode(' > ', array_map('strval', array_slice((array) ($hooks['active'] ?? []), -10))),
        ];
        if ($reference !== '') {
            $rows['Reference'] = $reference;
        }
        if (! empty($woo['active'])) {
            $rows['WooCommerce'] = (string) ($woo['version'] ?? 'unknown');
            $rows['HPOS'] = isset($woo['hpos']) && $woo['hpos'] !== null ? ($woo['hpos'] ? 'Enabled' : 'Disabled') : 'Unknown';
        }

        $html = '<details style="margin:24px 0;padding:12px 16px;border:1px solid rgba(127,127,127,.35);border-radius:10px;background:rgba(127,127,127,.08);color:inherit;font:14px/1.5 ui-sans-serif,system-ui,sans-serif">'
            . '<summary style="cursor:pointer;font-weight:700">WordPress context</summary>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:10px">'
            . '<caption style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap">WordPress context</caption>';
        foreach ($rows as $label => $value) {
            if ($value === '') {
                $value = '—';
            }
            $html .= '<tr><th scope="row" style="text-align:left;vertical-align:top;padding:4px 14px 4px 0;white-space:nowrap;opacity:.75">' . self::e($label) . '</th><td style="padding:4px 0;overflow-wrap:anywhere">' . self::e(self::text($value)) . '</td></tr>';
        }
        $html .= '</table>';

        $issues = array_slice((array) ($compat['issues'] ?? []), 0, 5);
        if ($issues !== []) {
            $html .= '<div style="margin-top:12px"><strong>Compatibility</strong><ul style="margin:6px 0 0 20px;padding:0">';
            foreach ($issues as $issue) {
                $issue = (array) $issue;
                $html .= '<li>' . self::e(strtoupper((string) ($issue['severity'] ?? 'info'))) . ': '
                    . self::e((string) ($issue['plugin'] ?? '')) . ' — ' . self::e(self::text((string) ($issue['message'] ?? ''))) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $recent = array_slice((array) ($hooks['recent'] ?? []), -5);
        if ($recent !== []) {
            $recent = Sanitizer::value('recent', $recent);
            $html .= '<details style="margin-top:12px"><summary style="cursor:pointer;font-weight:700">Recent hook arguments</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere;margin:8px 0 0;padding:10px;border-radius:8px;background:rgba(127,127,127,.12)">' . self::e(self::json($recent)) . '</pre></details>';
        }

        return $html . '</details>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function text(string $value): string
    {
        return Sanitizer::text($value);
    }

    private static function json(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $json === false ? '"[unencodable value]"' : $json;
    }

    private static function banner(string $title, string $backUrl): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeBack = function_exists('esc_url_raw') ? esc_url_raw($backUrl) : $backUrl;
        $back = $safeBack !== ''
            ? '<a href="' . htmlspecialchars($safeBack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="color:#fff;font-weight:700">Back to WP Aware Errors</a>'
            : '';

        return '<div role="status" style="position:fixed;z-index:100000;left:16px;right:16px;bottom:16px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:12px 16px;border-radius:12px;background:#1f130c;color:#ffd7cc;font:14px/1.4 ui-sans-serif,system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)">'
            . '<div><strong style="color:#fff">Sample preview</strong> · ' . $title . '. This error was thrown, then rendered by Spatie Ignition. It was not saved to history.</div>'
            . $back
            . '</div>';
    }
}
