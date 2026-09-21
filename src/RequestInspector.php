<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class RequestInspector
{
    public static function register(): void
    {
        if (! function_exists('add_filter')) return;
        add_filter('rest_request_before_callbacks', [self::class, 'captureRest'], PHP_INT_MIN, 3);
    }

    public static function captureRest(mixed $response, mixed $handler, mixed $request): mixed
    {
        if (is_object($request) && method_exists($request, 'get_route')) {
            try {
                Runtime::mergeRequestContext([
                    'rest_route' => (string) $request->get_route(),
                    'rest_method' => method_exists($request, 'get_method') ? (string) $request->get_method() : '',
                    'rest_params' => method_exists($request, 'get_params') ? Sanitizer::restParams($request->get_params()) : [],
                ]);
            } catch (Throwable) {}
        }
        return $response;
    }

    public static function restRoute(): string
    {
        $runtime = Runtime::requestContext();
        if (! empty($runtime['rest_route'])) return (string) $runtime['rest_route'];
        if (isset($_GET['rest_route'])) return (string) $_GET['rest_route'];
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $pos = strpos($uri, '/wp-json/');
        if ($pos !== false) {
            $route = substr($uri, $pos + strlen('/wp-json'));
            return (string) strtok($route, '?');
        }
        return '';
    }
}
