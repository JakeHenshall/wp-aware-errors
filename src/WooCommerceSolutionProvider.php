<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class WooCommerceSolutionProvider implements SolutionProvider
{
    public function supports(ExceptionData $error, array $context): bool
    {
        if (empty($context['woocommerce']['active'])) return false;
        $source = (array) ($context['likely_source'] ?? []);
        return ($source['kind'] ?? '') === 'plugin' || str_contains(strtolower($error->message), 'woocommerce') || str_contains(strtolower($error->message), 'wc_');
    }
    public function provide(ExceptionData $error, array $context): array
    {
        $woo = (array) ($context['woocommerce'] ?? []);
        $route = (string) ($woo['ajax_endpoint'] ?? '');
        return [[
            'title' => 'WooCommerce request context captured',
            'description' => 'WooCommerce ' . ($woo['version'] ?? 'unknown') . ($route !== '' ? " · wc-ajax={$route}" : '') . (isset($woo['hpos']) && $woo['hpos'] !== null ? ' · HPOS ' . ($woo['hpos'] ? 'enabled' : 'disabled') : ''),
            'action' => 'Check extension compatibility headers, the active Woo hook trail and whether the failing code assumes legacy order storage or a frontend-only cart/session.',
        ]];
    }
}
