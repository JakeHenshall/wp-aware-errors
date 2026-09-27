<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

/**
 * Sample error screens for Tools → WP Aware Errors.
 * These use the real renderer and are never written to history.
 */
final class ErrorPreview
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $definitions = null;

    /** @return list<array{slug:string,title:string,kicker:string,summary:string}> */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::definitions() as $slug => $scenario) {
            $catalog[] = [
                'slug' => $slug,
                'title' => (string) $scenario['title'],
                'kicker' => (string) $scenario['kicker'],
                'summary' => (string) $scenario['summary'],
            ];
        }
        return $catalog;
    }

    public static function has(string $slug): bool
    {
        return isset(self::definitions()[$slug]);
    }

    public static function send(string $slug, bool $framed): void
    {
        $scenario = self::definitions()[$slug] ?? null;
        if ($scenario === null) {
            return;
        }

        /** @var ExceptionData $error */
        $error = $scenario['error'];
        $context = self::context($error, $scenario);
        /** @var list<array{line:int,text:string,active:bool}> $code */
        $code = $scenario['code'];
        $back = function_exists('admin_url') ? admin_url('tools.php?page=wp-aware-errors') : '';

        if (! headers_sent()) {
            if (function_exists('status_header')) {
                status_header(200);
            }
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            if (function_exists('nocache_headers')) {
                nocache_headers();
            }
        }

        echo Renderer::document(
            $error,
            $context,
            $code,
            $framed ? '' : (string) $scenario['title'],
            $framed ? '' : $back
        );
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private static function context(ExceptionData $error, array $scenario): array
    {
        $context = ContextCollector::collect($error);
        $context['request'] = $scenario['request'];
        $context['hooks'] = $scenario['hooks'];
        $context['compatibility'] = $scenario['compatibility'] ?? ['issues' => []];
        if (isset($scenario['database'])) {
            $context['database'] = $scenario['database'];
        }
        if (isset($scenario['woocommerce'])) {
            $context['woocommerce'] = self::woo((array) ($context['woocommerce'] ?? []), (array) $scenario['woocommerce']);
        }
        return $context;
    }

    /** @param array<string,mixed> $live @param array<string,mixed> $story @return array<string,mixed> */
    private static function woo(array $live, array $story): array
    {
        if (empty($live['active'])) {
            $live = [
                'active' => true,
                'version' => '9.2.3 (sample)',
                'ajax_endpoint' => '',
                'hpos' => true,
                'cart_count' => 2,
                'cart_total' => '48.00',
                'checkout' => false,
                'cart_page' => false,
                'account_page' => false,
            ];
        }
        return array_merge($live, $story);
    }

    /** @return array<string,array<string,mixed>> */
    private static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $scenarios = [
            self::whiteScreen(),
            self::missingFunction(),
            self::missingFile(),
            self::missingClass(),
            self::undefinedMethod(),
            self::parseError(),
            self::memoryExhausted(),
            self::headersSent(),
            self::restFatal(),
        ];

        $definitions = [];
        foreach ($scenarios as $scenario) {
            $definitions[(string) $scenario['slug']] = $scenario;
        }
        self::$definitions = $definitions;
        return self::$definitions;
    }

    /** @return array<string,mixed> */
    private static function whiteScreen(): array
    {
        $file = self::theme('woocommerce/content-product.php');
        $line = 64;
        return self::scenario(
            'white-screen',
            'White screen of death',
            'Replaces a blank page',
            'Call to a member function get_price() on null',
            ExceptionData::sample('TypeError', 'Call to a member function get_price() on null', $file, $line, self::thrown($file, $line, 'example_theme_product_card')),
            self::codeAt($line, <<<'CODE'
<?php
/**
 * Theme override of the WooCommerce product card.
 */
defined('ABSPATH') || exit;

add_action('woocommerce_before_shop_loop_item_title', 'example_theme_product_card', 12);

function example_theme_product_card(): void {
    global $product;

    $price = $product->get_price();
    printf('<span class="price">%s</span>', esc_html($price));
}
CODE, '$product->get_price()'),
            self::request('GET', '/shop/'),
            self::hooks(
                ['wp', 'template_redirect', 'woocommerce_before_shop_loop_item_title'],
                [
                    'woocommerce_before_shop_loop_item_title' => [
                        'arg0' => ['__class' => 'WC_Product', 'id' => null],
                    ],
                ],
                [self::owner('woocommerce_before_shop_loop_item_title', 'example_theme_product_card()', 'Theme: example-theme', 12)]
            ),
            ['cart_count' => 0, 'cart_total' => '0.00', 'checkout' => false, 'cart_page' => false]
        );
    }

    /** @return array<string,mixed> */
    private static function missingFunction(): array
    {
        $file = self::plugin('example-shop', 'example-shop.php');
        $line = 48;
        return self::scenario(
            'missing-function',
            'Missing function',
            'Undefined function',
            'Call to undefined function wc_get_product()',
            ExceptionData::sample('Error', 'Call to undefined function wc_get_product()', $file, $line, self::thrown($file, $line, 'example_shop_add_badge')),
            self::codeAt($line, <<<'CODE'
<?php
/**
 * Plugin Name: Example Shop
 */

add_action('wp_ajax_example_shop_add_badge', 'example_shop_add_badge');

function example_shop_add_badge(): void {
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $product = wc_get_product($product_id);

    if (! $product) {
        wp_send_json_error('Missing product', 404);
    }

    wp_send_json_success(['sku' => $product->get_sku()]);
}
CODE, 'wc_get_product('),
            self::request('POST', '/wp-admin/admin-ajax.php', [
                'ajax' => true,
                'ajax_action' => 'example_shop_add_badge',
                'post' => ['action' => 'example_shop_add_badge', 'product_id' => '1204'],
            ]),
            self::hooks(
                ['admin_init', 'wp_ajax_example_shop_add_badge'],
                [
                    'wp_ajax_example_shop_add_badge' => [
                        'arg0' => ['__type' => 'array', 'count' => 2, 'keys' => ['action', 'product_id']],
                    ],
                ],
                [self::owner('wp_ajax_example_shop_add_badge', 'example_shop_add_badge()', 'Plugin: example-shop')]
            ),
            ['ajax_endpoint' => '', 'checkout' => false]
        );
    }

    /** @return array<string,mixed> */
    private static function missingFile(): array
    {
        $file = self::plugin('example-shop', 'example-shop.php');
        $missing = self::plugin('example-shop', 'vendor/autoload.php');
        $line = 21;
        $message = "Failed opening required '" . $missing . "' (include_path='.:/usr/local/lib/php')";
        return self::scenario(
            'missing-file',
            'Missing file',
            'Require failed',
            'vendor/autoload.php was required and is not on disk',
            ExceptionData::sample('Error', $message, $file, $line, self::thrown($file, $line, 'include')),
            self::codeAt($line, <<<'CODE'
<?php
/**
 * Plugin Name: Example Shop
 */
defined('ABSPATH') || exit;

$autoload = __DIR__ . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    // Deploy step skipped `composer install`.
}
require $autoload;

add_action('plugins_loaded', 'example_shop_boot');
CODE, 'require $autoload;'),
            self::request('GET', '/'),
            self::hooks(
                ['plugins_loaded'],
                [],
                [self::owner('plugins_loaded', 'example_shop_boot()', 'Plugin: example-shop')]
            )
        );
    }

    /** @return array<string,mixed> */
    private static function missingClass(): array
    {
        $file = self::plugin('example-shop', 'includes/class-example-shop-gateway.php');
        $line = 33;
        return self::scenario(
            'missing-class',
            'Missing class',
            'Class not found',
            'Class "Example\\Shop\\Gateway" not found',
            ExceptionData::sample('Error', 'Class "Example\\Shop\\Gateway" not found', $file, $line, self::thrown($file, $line, 'example_shop_register_gateway')),
            self::codeAt($line, <<<'CODE'
<?php
namespace Example\Shop;

add_filter('woocommerce_payment_gateways', __NAMESPACE__ . '\\example_shop_register_gateway');

function example_shop_register_gateway(array $gateways): array {
    $gateways[] = Gateway::class;
    return $gateways;
}
CODE, 'Gateway::class'),
            self::request('GET', '/checkout/'),
            self::hooks(
                ['plugins_loaded', 'woocommerce_payment_gateways'],
                [
                    'woocommerce_payment_gateways' => [
                        'arg0' => ['__type' => 'array', 'count' => 3, 'keys' => ['bacs', 'cheque', 'cod']],
                    ],
                ],
                [self::owner('woocommerce_payment_gateways', 'Example\\Shop\\example_shop_register_gateway()', 'Plugin: example-shop')]
            ),
            ['checkout' => true, 'cart_count' => 1, 'cart_total' => '24.00']
        );
    }

    /** @return array<string,mixed> */
    private static function undefinedMethod(): array
    {
        $file = self::plugin('example-shop', 'includes/checkout.php');
        $line = 77;
        return self::scenario(
            'undefined-method',
            'Undefined method',
            'Version mismatch',
            'Call to undefined method WC_Order::get_custom_status()',
            ExceptionData::sample('Error', 'Call to undefined method WC_Order::get_custom_status()', $file, $line, self::thrown($file, $line, 'example_shop_after_checkout')),
            self::codeAt($line, <<<'CODE'
<?php
add_action('woocommerce_checkout_order_processed', 'example_shop_after_checkout', 10, 3);

function example_shop_after_checkout(int $order_id, array $posted, WC_Order $order): void {
    if ($order->get_custom_status() !== 'awaiting-pickup') {
        return;
    }

    example_shop_notify_warehouse($order_id);
}
CODE, 'get_custom_status()'),
            self::request('POST', '/checkout/', [
                'post' => ['billing_email' => 'buyer@example.test', 'payment_method' => 'cod'],
            ]),
            self::hooks(
                ['wp', 'template_redirect', 'woocommerce_checkout_order_processed'],
                [
                    'woocommerce_checkout_order_processed' => [
                        'arg0' => 4821,
                        'arg1' => ['__type' => 'array', 'count' => 4, 'keys' => ['billing_email', 'payment_method']],
                        'arg2' => ['__class' => 'WC_Order', 'id' => 4821],
                    ],
                ],
                [self::owner('woocommerce_checkout_order_processed', 'example_shop_after_checkout()', 'Plugin: example-shop')]
            ),
            ['checkout' => true, 'cart_count' => 2, 'cart_total' => '48.00'],
            [
                'issues' => [[
                    'severity' => 'warning',
                    'plugin' => 'Example Shop',
                    'message' => 'Example Shop declares WC tested up to 8.4, but a newer WooCommerce is active. An undefined method often follows an untested major bump.',
                ]],
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function parseError(): array
    {
        $file = self::plugin('example-shop', 'includes/templates.php');
        $line = 18;
        return self::scenario(
            'parse-error',
            'Parse error',
            'Syntax error',
            'syntax error, unexpected token "}", expecting ";"',
            ExceptionData::sample('PHP Fatal Error', 'syntax error, unexpected token "}", expecting ";"', $file, $line, self::fatal($file, $line)),
            self::codeAt($line, <<<'CODE'
<?php
function example_shop_price_html(string $html, $product): string {
    if (! $product) {
        return $html
    }

    return '<span class="example-price">' . $html . '</span>';
}
CODE, '}'),
            self::request('GET', '/'),
            self::hooks(['plugins_loaded'], [], [self::owner('plugins_loaded', 'example_shop_boot()', 'Plugin: example-shop')])
        );
    }

    /** @return array<string,mixed> */
    private static function memoryExhausted(): array
    {
        $file = self::plugin('example-shop', 'includes/export.php');
        $line = 54;
        return self::scenario(
            'memory',
            'Memory exhausted',
            'Out of memory',
            'Allowed memory size of 134217728 bytes exhausted',
            ExceptionData::sample(
                'PHP Fatal Error',
                'Allowed memory size of 134217728 bytes exhausted (tried to allocate 2048000 bytes)',
                $file,
                $line,
                self::fatal($file, $line)
            ),
            self::codeAt($line, <<<'CODE'
<?php
function example_shop_export_catalog(): array {
    $rows = [];
    $ids = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    foreach ($ids as $id) {
        $product = wc_get_product($id);
        $rows[] = $product ? $product->get_data() : [];
    }

    return $rows;
}
CODE, 'get_data()'),
            self::request('GET', '/wp-admin/admin.php?page=example-shop-export'),
            self::hooks(
                ['admin_init', 'pre_get_posts'],
                [
                    'pre_get_posts' => [
                        'arg0' => ['__class' => 'WP_Query'],
                    ],
                ],
                [self::owner('pre_get_posts', 'example_shop_export_catalog()', 'Plugin: example-shop')]
            ),
            [],
            ['issues' => []],
            [
                'enabled' => true,
                'queries' => [
                    [
                        'sql' => "SELECT ID FROM wp_posts WHERE post_type = 'product' AND post_status = 'publish'",
                        'seconds' => 1.842,
                        'caller' => 'example_shop_export_catalog(), WP_Query->get_posts',
                    ],
                    [
                        'sql' => "SELECT * FROM wp_postmeta WHERE post_id IN (1204,1205,1206) AND meta_key LIKE '\\_price%'",
                        'seconds' => 0.41,
                        'caller' => 'WC_Product_Data_Store_CPT->read',
                    ],
                ],
            ]
        );
    }

    /** @return array<string,mixed> */
    private static function headersSent(): array
    {
        $file = self::plugin('example-shop', 'includes/redirects.php');
        $started = self::theme('functions.php');
        $line = 27;
        $message = 'Cannot modify header information - headers already sent by (output started at ' . $started . ':1)';
        return self::scenario(
            'headers-sent',
            'Headers already sent',
            'Only if thrown',
            'Normally a PHP warning. This screen appears only when that warning is thrown.',
            ExceptionData::sample('ErrorException', $message, $file, $line, self::thrown($file, $line, 'example_shop_redirect_legacy')),
            self::codeAt($line, <<<'CODE'
<?php
add_action('template_redirect', 'example_shop_redirect_legacy');

function example_shop_redirect_legacy(): void {
    if (! is_page('old-shop')) {
        return;
    }

    wp_redirect(home_url('/shop/'), 301);
    exit;
}
CODE, 'wp_redirect('),
            self::request('GET', '/old-shop/'),
            self::hooks(
                ['wp', 'template_redirect'],
                [],
                [self::owner('template_redirect', 'example_shop_redirect_legacy()', 'Plugin: example-shop')]
            )
        );
    }

    /** @return array<string,mixed> */
    private static function restFatal(): array
    {
        $file = self::plugin('example-shop', 'includes/rest.php');
        $line = 41;
        return self::scenario(
            'rest-route',
            'REST API fatal',
            'REST request',
            'Call to undefined function example_shop_schema()',
            ExceptionData::sample('Error', 'Call to undefined function example_shop_schema()', $file, $line, self::restTrace($file, $line)),
            self::codeAt($line, <<<'CODE'
<?php
add_action('rest_api_init', function (): void {
    register_rest_route('example-shop/v1', '/products', [
        'methods' => 'GET',
        'callback' => 'example_shop_rest_products',
        'permission_callback' => '__return_true',
    ]);
});

function example_shop_rest_products(WP_REST_Request $request): WP_REST_Response {
    return rest_ensure_response(example_shop_schema($request->get_param('per_page')));
}
CODE, 'example_shop_schema('),
            self::request('GET', '/wp-json/example-shop/v1/products?per_page=10', [
                'rest' => true,
                'rest_route' => '/example-shop/v1/products',
                'rest_method' => 'GET',
                'rest_params' => ['per_page' => '10'],
                'query' => ['per_page' => '10'],
                'headers' => [
                    'host' => 'example.test',
                    'accept' => 'application/json',
                    'user-agent' => 'Mozilla/5.0 (sample preview)',
                ],
            ]),
            self::hooks(
                ['rest_api_init', 'rest_pre_dispatch'],
                [
                    'rest_pre_dispatch' => [
                        'arg0' => null,
                        'arg1' => ['__class' => 'WP_REST_Server'],
                        'arg2' => ['__class' => 'WP_REST_Request', 'route' => '/example-shop/v1/products', 'method' => 'GET'],
                    ],
                ],
                [self::owner('rest_api_init', 'example_shop_rest_products()', 'Plugin: example-shop')]
            )
        );
    }

    /**
     * @param list<array{line:int,text:string,active:bool}> $code
     * @param array<string,mixed> $request
     * @param array<string,mixed> $hooks
     * @param array<string,mixed> $woo
     * @param array<string,mixed> $compatibility
     * @param array<string,mixed>|null $database
     * @return array<string,mixed>
     */
    private static function scenario(
        string $slug,
        string $title,
        string $kicker,
        string $summary,
        ExceptionData $error,
        array $code,
        array $request,
        array $hooks,
        array $woo = [],
        array $compatibility = ['issues' => []],
        ?array $database = null
    ): array {
        $scenario = [
            'slug' => $slug,
            'title' => $title,
            'kicker' => $kicker,
            'summary' => $summary,
            'error' => $error,
            'code' => $code,
            'request' => $request,
            'hooks' => $hooks,
            'compatibility' => $compatibility,
        ];
        if ($woo !== []) {
            $scenario['woocommerce'] = $woo;
        }
        if ($database !== null) {
            $scenario['database'] = $database;
        }
        return $scenario;
    }

    /** @param list<array<string,mixed>> $callers @return list<array<string,mixed>> */
    private static function thrown(string $file, int $line, string $callback, array $callers = []): array
    {
        if ($callers === []) {
            $callers = [
                ['file' => self::core('wp-includes/class-wp-hook.php'), 'line' => 324, 'function' => $callback],
                ['file' => self::core('wp-includes/class-wp-hook.php'), 'line' => 348, 'class' => 'WP_Hook', 'type' => '->', 'function' => 'apply_filters'],
                ['file' => self::core('wp-includes/plugin.php'), 'line' => 517, 'function' => 'do_action'],
                ['file' => self::core('wp-includes/class-wp.php'), 'line' => 818, 'class' => 'WP', 'type' => '->', 'function' => 'main'],
            ];
        }

        return array_merge([[
            'file' => $file,
            'line' => $line,
            'function' => '{throw}',
        ]], $callers);
    }

    /** @return list<array<string,mixed>> */
    private static function restTrace(string $file, int $line): array
    {
        return self::thrown($file, $line, 'example_shop_rest_products', [
            ['file' => self::core('wp-includes/rest-api/class-wp-rest-server.php'), 'line' => 1180, 'function' => 'example_shop_rest_products'],
            ['file' => self::core('wp-includes/rest-api/class-wp-rest-server.php'), 'line' => 1024, 'class' => 'WP_REST_Server', 'type' => '->', 'function' => 'respond_to_request'],
            ['file' => self::core('wp-includes/rest-api/class-wp-rest-server.php'), 'line' => 445, 'class' => 'WP_REST_Server', 'type' => '->', 'function' => 'dispatch'],
            ['file' => self::core('wp-includes/rest-api.php'), 'line' => 448, 'function' => 'rest_get_server'],
        ]);
    }

    /** @return list<array<string,mixed>> */
    private static function fatal(string $file, int $line): array
    {
        return [[
            'file' => $file,
            'line' => $line,
            'function' => '{fatal}',
        ]];
    }

    /** @return list<array{line:int,text:string,active:bool}> */
    private static function codeAt(int $errorLine, string $source, string $activeNeedle): array
    {
        $lines = preg_split("/\n/", rtrim(ltrim($source, "\n"), "\n"));
        if (! is_array($lines)) {
            $lines = [];
        }
        $activeIndex = 0;
        foreach ($lines as $index => $text) {
            if (str_contains((string) $text, $activeNeedle)) {
                $activeIndex = (int) $index;
                break;
            }
        }

        $start = $errorLine - $activeIndex;
        $out = [];
        foreach ($lines as $index => $text) {
            $line = $start + (int) $index;
            $out[] = [
                'line' => $line,
                'text' => rtrim((string) $text, "\r"),
                'active' => (int) $index === $activeIndex,
            ];
        }
        return $out;
    }

    /** @param array<string,array<string,mixed>> $argsByHook @param list<array<string,mixed>> $ownership @return array<string,mixed> */
    private static function hooks(array $stack, array $argsByHook, array $ownership): array
    {
        $recent = [];
        foreach ($stack as $name) {
            $recent[] = [
                'name' => $name,
                'time' => microtime(true),
                'args' => $argsByHook[$name] ?? [],
            ];
        }
        return [
            'active' => $stack,
            'recent' => $recent,
            'ownership' => $ownership,
        ];
    }

    /** @return array<string,mixed> */
    private static function owner(string $hook, string $callback, string $component, int $priority = 10): array
    {
        return [
            'hook' => $hook,
            'priority' => $priority,
            'callback' => $callback,
            'component' => $component,
        ];
    }

    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private static function request(string $method, string $uri, array $extra = []): array
    {
        return array_merge([
            'method' => $method,
            'uri' => $uri,
            'query' => [],
            'post' => [],
            'headers' => [
                'host' => 'example.test',
                'accept' => 'text/html',
                'user-agent' => 'Mozilla/5.0 (sample preview)',
            ],
            'ajax' => false,
            'ajax_action' => '',
            'admin_post_action' => '',
            'cron' => false,
            'rest' => false,
            'rest_route' => '',
            'rest_method' => '',
            'rest_params' => [],
            'cli' => false,
        ], $extra);
    }

    private static function plugin(string $slug, string $relative): string
    {
        $root = defined('WP_PLUGIN_DIR') ? (string) WP_PLUGIN_DIR : '';
        if ($root === '' && defined('WP_CONTENT_DIR')) {
            $root = rtrim((string) WP_CONTENT_DIR, '/\\') . '/plugins';
        }
        if ($root === '') {
            $root = 'wp-content/plugins';
        }
        return rtrim($root, '/\\') . '/' . $slug . '/' . ltrim($relative, '/');
    }

    private static function theme(string $relative): string
    {
        $root = function_exists('get_theme_root') ? (string) get_theme_root() : '';
        if ($root === '' && defined('WP_CONTENT_DIR')) {
            $root = rtrim((string) WP_CONTENT_DIR, '/\\') . '/themes';
        }
        if ($root === '') {
            $root = 'wp-content/themes';
        }
        return rtrim($root, '/\\') . '/example-theme/' . ltrim($relative, '/');
    }

    private static function core(string $relative): string
    {
        $root = defined('ABSPATH') ? rtrim((string) ABSPATH, '/\\') : '';
        return ($root !== '' ? $root . '/' : '') . ltrim($relative, '/');
    }
}
