<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

final class Sanitizer
{
    /** @var list<string> */
    private const SENSITIVE = [
        'password','passwd','pwd','pass','secret','token','access_token','refresh_token','authorization',
        'cookie','set-cookie','api_key','apikey','client_secret','private_key','_wpnonce','nonce',
        'credit_card','card_number','cvv','session','auth_key','secure_auth_key','logged_in_key',
    ];

    public static function value(string $key, mixed $value, int $depth = 0): mixed
    {
        return self::dump($value, $key, $depth, 3, 30, 500, true);
    }

    public static function argument(mixed $value, int $depth = 0): mixed
    {
        return self::dump($value, 'argument', $depth, 1, 8, 120, false);
    }

    public static function restParams(mixed $value): mixed
    {
        return self::dump($value, 'params', 0, 1, 8, 120, false);
    }

    public static function sql(string $query): string
    {
        $query = preg_replace("/(password|passwd|pwd|token|secret|api_key)\\s*=\\s*(['\"]).*?\\2/i", '$1=\'••••\'', $query) ?? $query;
        return strlen($query) > 1000 ? substr($query, 0, 1000) . '…' : $query;
    }

    private static function dump(mixed $value, string $key, int $depth, int $maxDepth, int $maxKeys, int $maxString, bool $expandRestParams): mixed
    {
        if (self::sensitive($key)) return '••••••••';
        if ($depth > $maxDepth) return '[depth limited]';

        if (is_array($value)) {
            $count = count($value);
            if ($count > $maxKeys) {
                $keys = [];
                $n = 0;
                foreach ($value as $childKey => $_) {
                    if ($n++ >= $maxKeys) break;
                    $label = (string) $childKey;
                    $keys[] = strlen($label) > 40 ? substr($label, 0, 40) . '…' : $label;
                }
                return [
                    '__type' => 'array',
                    'count' => $count,
                    'keys' => $keys,
                ];
            }
            $out = [];
            $n = 0;
            foreach ($value as $childKey => $childValue) {
                if ($n++ >= $maxKeys) {
                    $out['…'] = '[truncated]';
                    break;
                }
                $out[(string) $childKey] = self::dump($childValue, (string) $childKey, $depth + 1, $maxDepth, $maxKeys, $maxString, $expandRestParams);
            }
            return $out;
        }
        if (is_object($value)) return self::object($value, $depth, $maxDepth, $maxKeys, $maxString, $expandRestParams);
        if (is_resource($value)) return '[resource]';
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) return $value;
        $text = (string) $value;
        return strlen($text) > $maxString ? substr($text, 0, $maxString) . '…' : $text;
    }

    private static function object(object $value, int $depth, int $maxDepth, int $maxKeys, int $maxString, bool $expandRestParams): array|string
    {
        $class = $value::class;
        if ($class === 'WP_REST_Request' && method_exists($value, 'get_route')) {
            try {
                $out = [
                    '__class' => $class,
                    'route' => (string) $value->get_route(),
                    'method' => method_exists($value, 'get_method') ? (string) $value->get_method() : '',
                ];
                if ($expandRestParams && method_exists($value, 'get_params')) {
                    $out['params'] = self::dump($value->get_params(), 'params', $depth + 1, $maxDepth, $maxKeys, $maxString, false);
                }
                return $out;
            } catch (Throwable) {}
        }
        if ($class === 'WP_Post') {
            return ['__class' => $class, 'ID' => (int) ($value->ID ?? 0), 'post_type' => (string) ($value->post_type ?? '')];
        }
        if (str_contains($class, 'WC_Order') && method_exists($value, 'get_id')) {
            try { return ['__class' => $class, 'id' => (int) $value->get_id()]; } catch (Throwable) {}
        }
        return ['__class' => $class];
    }

    private static function sensitive(string $key): bool
    {
        $key = strtolower(str_replace(['-', ' '], '_', $key));
        foreach (self::SENSITIVE as $needle) {
            if ($key === $needle || str_contains($key, $needle)) return true;
        }
        return false;
    }
}
