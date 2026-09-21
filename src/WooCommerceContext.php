<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class WooCommerceContext
{
    public static function version(): string
    {
        if (defined('WC_VERSION')) return (string) WC_VERSION;
        if (defined('WOOCOMMERCE_VERSION')) return (string) WOOCOMMERCE_VERSION;
        return '';
    }

    /** @return array<string,mixed> */
    public static function collect(): array
    {
        $version = self::version();
        if ($version === '' && ! class_exists('WooCommerce')) return ['active' => false];

        $data = [
            'active' => true,
            'version' => $version,
            'ajax_endpoint' => isset($_REQUEST['wc-ajax']) ? Sanitizer::value('wc-ajax', $_REQUEST['wc-ajax']) : '',
            'hpos' => null,
            'cart_count' => null,
            'cart_total' => null,
            'checkout' => function_exists('is_checkout') ? (bool) is_checkout() : null,
            'cart_page' => function_exists('is_cart') ? (bool) is_cart() : null,
            'account_page' => function_exists('is_account_page') ? (bool) is_account_page() : null,
        ];

        $orderUtil = 'Automattic\\WooCommerce\\Utilities\\OrderUtil';
        if (class_exists($orderUtil) && method_exists($orderUtil, 'custom_orders_table_usage_is_enabled')) {
            try { $data['hpos'] = (bool) $orderUtil::custom_orders_table_usage_is_enabled(); } catch (Throwable) {}
        }

        if (function_exists('WC')) {
            try {
                $wc = WC();
                if (is_object($wc) && isset($wc->cart) && is_object($wc->cart)) {
                    if (method_exists($wc->cart, 'get_cart_contents_count')) $data['cart_count'] = (int) $wc->cart->get_cart_contents_count();
                    if (method_exists($wc->cart, 'get_total')) $data['cart_total'] = Sanitizer::value('cart_total', $wc->cart->get_total('edit'));
                }
            } catch (Throwable) {}
        }

        return $data;
    }
}
