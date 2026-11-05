<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (! defined('ABSPATH')) {
    $testRoot = rtrim(sys_get_temp_dir(), '/\\') . '/wp-aware-errors-tests';
    if (! is_dir($testRoot)) {
        mkdir($testRoot, 0777, true);
    }
    define('ABSPATH', $testRoot . '/');
}
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}
if (! defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
}
if (! defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

$GLOBALS['wp_test_options'] = [];
$GLOBALS['wp_test_option_autoload'] = [];
$GLOBALS['wp_test_environment'] = 'local';
$GLOBALS['wp_test_plugins'] = [];
$GLOBALS['wp_test_file_data'] = [];
$GLOBALS['wp_test_theme_root'] = WP_CONTENT_DIR . '/themes';
$GLOBALS['wp_test_theme'] = null;
$GLOBALS['wp_test_current_filter'] = '';
$GLOBALS['wp_test_hooks'] = [];

if (! class_exists('WP_Error')) {
    final class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = '',
            private mixed $data = null
        ) {}

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}

final class WP_Test_Theme
{
    /** @param array<string,string> $data */
    public function __construct(private array $data = [], private ?self $parent = null) {}

    public function exists(): bool
    {
        return $this->data !== [];
    }

    public function get(string $key): string
    {
        return (string) ($this->data[$key] ?? '');
    }

    public function get_stylesheet(): string
    {
        return (string) ($this->data['stylesheet'] ?? '');
    }

    public function parent(): ?self
    {
        return $this->parent;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['wp_test_options'][$name] ?? $default;
    }
}
if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value, mixed $autoload = null): bool
    {
        $GLOBALS['wp_test_options'][$name] = $value;
        $GLOBALS['wp_test_option_autoload'][$name] = $autoload;
        return true;
    }
}
if (! function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset($GLOBALS['wp_test_options'][$name]);
        return true;
    }
}
if (! function_exists('get_site_option')) {
    function get_site_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['wp_test_options'][$name] ?? $default;
    }
}
if (! function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}
if (! function_exists('wp_get_environment_type')) {
    function wp_get_environment_type(): string
    {
        return (string) $GLOBALS['wp_test_environment'];
    }
}
if (! function_exists('get_plugins')) {
    function get_plugins(): array
    {
        return $GLOBALS['wp_test_plugins'];
    }
}
if (! function_exists('get_file_data')) {
    function get_file_data(string $file, array $headers, string $context = ''): array
    {
        $data = $GLOBALS['wp_test_file_data'][$file] ?? [];
        $out = [];
        foreach ($headers as $key => $header) {
            $out[$key] = (string) ($data[$key] ?? '');
        }
        return $out;
    }
}
if (! function_exists('get_theme_root')) {
    function get_theme_root(): string
    {
        return (string) $GLOBALS['wp_test_theme_root'];
    }
}
if (! function_exists('wp_get_theme')) {
    function wp_get_theme(): WP_Test_Theme
    {
        return $GLOBALS['wp_test_theme'] instanceof WP_Test_Theme ? $GLOBALS['wp_test_theme'] : new WP_Test_Theme();
    }
}
if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}
if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['wp_test_hooks'][$hook][] = [$callback, $priority, $acceptedArgs];
        return true;
    }
}
if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return add_filter($hook, $callback, $priority, $acceptedArgs);
    }
}
if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return true;
    }
}
if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return $capability === 'manage_options';
    }
}
if (! function_exists('current_filter')) {
    function current_filter(): string
    {
        return (string) $GLOBALS['wp_test_current_filter'];
    }
}
if (! function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return false;
    }
}
if (! function_exists('wp_doing_cron')) {
    function wp_doing_cron(): bool
    {
        return false;
    }
}
if (! function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string
    {
        return $show === 'language' ? 'en-US' : '';
    }
}
if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}
if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}
if (! function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('esc_attr')) {
    function esc_attr(string $value): string
    {
        return esc_html($value);
    }
}
if (! function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return $value;
    }
}
if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}
if (! function_exists('nocache_headers')) {
    function nocache_headers(): void {}
}
if (! function_exists('status_header')) {
    function status_header(int $code): void {}
}
if (! function_exists('wp_die')) {
    function wp_die(mixed $message = '', string $title = '', array $args = []): void {}
}
