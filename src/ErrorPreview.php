<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

/**
 * Admin previews throw a real PHP error and render it with Spatie Ignition.
 * Nothing here is written to the error history.
 */
final class ErrorPreview
{
    /** @return list<array{slug:string,title:string,kicker:string,summary:string}> */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::definitions() as $slug => $scenario) {
            $catalog[] = [
                'slug' => $slug,
                'title' => $scenario['title'],
                'kicker' => $scenario['kicker'],
                'summary' => $scenario['summary'],
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

        if (! IgnitionPage::available()) {
            if (! headers_sent()) {
                status_header(500);
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo 'Spatie Ignition is not installed. From the wp-aware-errors plugin directory, run composer install.';
            return;
        }

        require_once dirname(__DIR__) . '/demo/triggers.php';
        $throwable = self::capture($slug, $scenario['throw']);
        $back = function_exists('admin_url') ? admin_url('tools.php?page=wp-aware-errors') : '';
        IgnitionPage::render(
            $throwable,
            self::context($throwable),
            true,
            $framed ? '' : $scenario['title'],
            $framed ? '' : $back
        );
    }

    /** @param callable():void $throw */
    private static function capture(string $slug, callable $throw): Throwable
    {
        try {
            $throw();
        } catch (Throwable $throwable) {
            return $throwable;
        } finally {
            if ($slug === 'headers-sent') {
                restore_error_handler();
            }
        }

        throw new \RuntimeException('The preview did not raise an error.');
    }

    /** @return array<string,mixed>|null */
    private static function context(Throwable $throwable): ?array
    {
        try {
            return ContextCollector::collect(ExceptionData::fromThrowable($throwable));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,array{title:string,kicker:string,summary:string,throw:callable():void}> */
    private static function definitions(): array
    {
        return [
            'white-screen' => [
                'title' => 'White screen of death',
                'kicker' => 'Error',
                'summary' => 'Call to a member function get_price() on null',
                'throw' => 'wp_aware_demo_null_method',
            ],
            'missing-function' => [
                'title' => 'Missing function',
                'kicker' => 'Error',
                'summary' => 'Call to undefined function wp_aware_demo_function_that_does_not_exist()',
                'throw' => 'wp_aware_demo_undefined_function',
            ],
            'missing-file' => [
                'title' => 'Missing file',
                'kicker' => 'Error',
                'summary' => 'require of a file that is not on disk',
                'throw' => 'wp_aware_demo_missing_file',
            ],
            'missing-class' => [
                'title' => 'Missing class',
                'kicker' => 'Error',
                'summary' => 'Class "WP_Aware_Demo_Missing_Gateway" not found',
                'throw' => 'wp_aware_demo_missing_class',
            ],
            'undefined-method' => [
                'title' => 'Undefined method',
                'kicker' => 'Error',
                'summary' => 'Call to undefined method WP_Aware_Demo_Order::get_custom_status()',
                'throw' => 'wp_aware_demo_undefined_method',
            ],
            'parse-error' => [
                'title' => 'Parse error',
                'kicker' => 'ParseError',
                'summary' => 'syntax error from including a broken PHP file',
                'throw' => 'wp_aware_demo_parse_error',
            ],
            'headers-sent' => [
                'title' => 'Headers already sent',
                'kicker' => 'Warning thrown',
                'summary' => 'PHP\'s real headers-already-sent warning, thrown the way Ignition does',
                'throw' => 'wp_aware_demo_headers_sent',
            ],
        ];
    }
}
