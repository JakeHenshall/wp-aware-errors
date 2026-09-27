<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

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
    public static function render(Throwable $throwable, ?array $context = null, bool $preview = false, string $previewTitle = '', string $backUrl = ''): void
    {
        $ignition = Ignition::make()
            ->shouldDisplayException(true)
            ->runningInProductionEnvironment(false)
            ->setTheme(Settings::theme() === 'light' ? 'light' : 'dark')
            ->addSolutionProviders([new IgnitionSolutionBridge()]);

        if (defined('ABSPATH')) {
            $ignition->applicationPath((string) ABSPATH);
        }

        $ignition->configureFlare(static function ($flare) use ($context): void {
            if (! is_object($flare) || ! method_exists($flare, 'group') || $context === null) {
                return;
            }
            $flare->group('WordPress', self::wordPressContext($context));
        });

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
        }

        $ignition->renderException($throwable);
    }

    /** @param array<string,mixed> $context @return array<string,string> */
    private static function wordPressContext(array $context): array
    {
        $installation = (array) ($context['installation'] ?? []);
        $environment = (array) ($context['environment'] ?? []);
        $request = (array) ($context['request'] ?? []);
        $hooks = (array) ($context['hooks'] ?? []);
        $source = (array) ($context['likely_source'] ?? []);
        $theme = $environment['theme'] ?? null;
        $themeName = is_array($theme) ? (string) ($theme['name'] ?? '') : '';

        return [
            'Installation' => (string) ($installation['label'] ?? ''),
            'WordPress' => (string) ($environment['wordpress'] ?? ''),
            'PHP' => (string) ($environment['php'] ?? PHP_VERSION),
            'Environment' => (string) ($environment['environment_type'] ?? ''),
            'Theme' => $themeName,
            'Likely source' => (string) ($source['label'] ?? ''),
            'Hook' => implode(' > ', array_map('strval', (array) ($hooks['active'] ?? []))),
            'Request' => trim((string) ($request['method'] ?? '') . ' ' . (string) ($request['uri'] ?? '')),
        ];
    }

    private static function banner(string $title, string $backUrl): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $back = $backUrl !== ''
            ? '<a href="' . htmlspecialchars($backUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="color:#fff;font-weight:700">Back to WP Aware Errors</a>'
            : '';

        return '<div style="position:fixed;z-index:100000;left:16px;right:16px;bottom:16px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:12px 16px;border-radius:12px;background:#1f130c;color:#ffd7cc;font:14px/1.4 ui-sans-serif,system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)">'
            . '<div><strong style="color:#fff">Sample preview</strong> · ' . $title . '. This error was thrown, then rendered by Spatie Ignition. It was not saved to history.</div>'
            . $back
            . '</div>';
    }
}
