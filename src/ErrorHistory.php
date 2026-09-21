<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class ErrorHistory
{
    private const OPTION = 'wp_aware_errors_history_v1';

    /** @param array<string,mixed> $context */
    public static function store(ExceptionData $error, array $context): void
    {
        if (defined('WP_AWARE_ERRORS_HISTORY') && WP_AWARE_ERRORS_HISTORY === false) return;
        if (! function_exists('get_option') || ! function_exists('update_option')) return;
        try {
            $history = get_option(self::OPTION, []);
            if (! is_array($history)) $history = [];
            $source = (array) ($context['likely_source'] ?? []);
            $request = (array) ($context['request'] ?? []);
            $history[] = [
                'id' => substr(hash('sha256', $error->time . $error->file . $error->line . $error->message), 0, 16),
                'time' => $error->time,
                'type' => $error->type,
                'message' => Sanitizer::value('message', $error->message),
                'file' => FrameClassifier::relative($error->file),
                'line' => $error->line,
                'source' => (string) ($source['label'] ?? 'Unknown'),
                'source_kind' => (string) ($source['kind'] ?? 'unknown'),
                'method' => (string) ($request['method'] ?? ''),
                'uri' => Sanitizer::value('uri', $request['uri'] ?? ''),
                'ajax_action' => Sanitizer::value('action', $request['ajax_action'] ?? ''),
                'rest_route' => Sanitizer::value('route', $request['rest_route'] ?? ''),
            ];
            $limit = defined('WP_AWARE_ERRORS_HISTORY_LIMIT') ? max(1, (int) WP_AWARE_ERRORS_HISTORY_LIMIT) : 30;
            $history = array_slice($history, -$limit);
            update_option(self::OPTION, $history, false);
        } catch (Throwable) {}
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        if (! function_exists('get_option')) return [];
        $history = get_option(self::OPTION, []);
        return is_array($history) ? array_values(array_reverse($history)) : [];
    }

    public static function clear(): void
    {
        if (function_exists('delete_option')) delete_option(self::OPTION);
    }
}
