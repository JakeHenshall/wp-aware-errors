<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

final class Sanitizer
{
    /** @var list<string> */
    private const SENSITIVE = [
        'password', 'passwd', 'pwd', 'pass', 'passcode', 'passphrase', 'secret', 'token',
        'access_token', 'refresh_token', 'authorization', 'authentication', 'auth', 'oauth',
        'bearer', 'signature', 'cookie', 'set_cookie', 'api_key', 'apikey', 'client_secret',
        'private_key', 'privatekey', '_wpnonce', 'wpnonce', 'nonce', 'security', 'credit_card',
        'card_number', 'cvv', 'otp', 'pin', 'session', 'auth_key', 'secure_auth_key',
        'logged_in_key',
    ];

    /** @var list<string> */
    private const URL_KEYS = [
        'uri', 'url', 'href', 'src', 'referer', 'referrer', 'origin', 'location', 'redirect',
        'redirect_to', 'return_url', 'next', 'link', 'image', 'avatar',
    ];

    /** @var array<string,string> */
    private const SECRET_PATTERNS = [
        '/((?:user_pass|password|passwords?|passcodes?|passphrases?|passwd|pass|pwd|secrets?|tokens?|api[_-]?keys?|client[_-]?secrets?|private[_-]?keys?|authorization|authentication|auth|cookies?|wpnonce|nonces?|security|sessions?|card_number|cvv|otp|pin)["\']?\s*[=:]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s&,;]+)/i' => '$1••••',
        '/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/]+=*/i' => '$1 ••••',
        '/\b(?:sk|pk|rk)[-_](?:live|test)[-_][A-Za-z0-9_-]{4,}\b/i' => '••••',
        '/\bsk-[A-Za-z0-9_-]{8,}\b/i' => '••••',
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
        return self::clip(self::text(self::clip($query, 4000)), 1000);
    }

    /** Redacts common secret assignments and bearer tokens in free-form text. */
    public static function text(string $value): string
    {
        foreach (self::SECRET_PATTERNS as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }

    /** Redacts credentials and sensitive query parameters while keeping the URL readable. */
    public static function url(string $url): string
    {
        $url = self::clip($url, 1000);
        $url = preg_replace('#(//)[^/\s]+@#', '$1••••@', $url) ?? $url;
        $url = preg_replace_callback('/([?&#])([^=&#\s]+)=([^&#]*)/', static function (array $matches): string {
            $key = urldecode($matches[2]);
            return $matches[1] . $matches[2] . '=' . (self::sensitive($key) ? '••••' : $matches[3]);
        }, $url) ?? $url;

        return self::clip($url, 1000);
    }

    private static function dump(mixed $value, string $key, int $depth, int $maxDepth, int $maxKeys, int $maxString, bool $expandRestParams): mixed
    {
        if (self::sensitive($key)) {
            return '••••••••';
        }
        if ($depth > $maxDepth) {
            return '[depth limited]';
        }

        if (is_array($value)) {
            $count = count($value);
            if ($count > $maxKeys) {
                $keys = [];
                $n = 0;
                foreach ($value as $childKey => $_) {
                    if ($n++ >= $maxKeys) {
                        break;
                    }
                    $label = (string) $childKey;
                    $keys[] = self::clip($label, 40);
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
        if (is_object($value)) {
            return self::object($value, $depth, $maxDepth, $maxKeys, $maxString, $expandRestParams);
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = self::clip((string) $value, max($maxString * 4, 400));
        if (self::urlKey($key)) {
            return self::url($text);
        }

        return self::clip(self::text($text), $maxString);
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
            } catch (Throwable) {
            }
        }
        if ($class === 'WP_Post') {
            return ['__class' => $class, 'ID' => (int) ($value->ID ?? 0), 'post_type' => (string) ($value->post_type ?? '')];
        }
        if (str_contains($class, 'WC_Order') && method_exists($value, 'get_id')) {
            try {
                return ['__class' => $class, 'id' => (int) $value->get_id()];
            } catch (Throwable) {
            }
        }

        return ['__class' => $class];
    }

    private static function sensitive(string $key): bool
    {
        $key = str_replace(['-', ' '], '_', $key);
        $key = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key) ?? $key;
        $key = strtolower($key);
        $parts = explode('_', $key);

        foreach (self::SENSITIVE as $needle) {
            $needle = str_replace('-', '_', strtolower($needle));
            if ($key === $needle
                || $key === $needle . 's'
                || in_array($needle, $parts, true)
                || in_array($needle . 's', $parts, true)
                || str_contains($key, $needle . '_')
                || str_contains($key, '_' . $needle)
                || (strlen($needle) >= 5 && str_starts_with($key, $needle))) {
                return true;
            }
        }

        return false;
    }

    private static function urlKey(string $key): bool
    {
        $key = str_replace(['-', ' '], '_', $key);
        $key = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key) ?? $key;
        $key = strtolower($key);

        return in_array($key, self::URL_KEYS, true)
            || str_contains($key, 'referer')
            || str_ends_with($key, '_url')
            || str_ends_with($key, '_uri');
    }

    private static function clip(string $value, int $max): string
    {
        if ($max < 1 || strlen($value) <= $max) {
            return $value;
        }

        $cut = function_exists('mb_strcut') ? (string) mb_strcut($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
        return $cut . '…';
    }
}
