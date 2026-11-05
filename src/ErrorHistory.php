<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

final class ErrorHistory
{
    private const OPTION = 'wp_aware_errors_history_v1';
    private const SCHEMA_OPTION = 'wp_aware_errors_history_schema';
    private const LEGACY_NOTICE_OPTION = 'wp_aware_errors_history_legacy_notice';
    private const QUARANTINE_OPTION = 'wp_aware_errors_history_quarantine';
    private const EARLY_OPTION = 'wp_aware_errors_early_fatal_references';
    private const SCHEMA_VERSION = 2;

    /** @param array<string,mixed> $context */
    public static function store(ExceptionData $error, array $context, string $reference = ''): void
    {
        $reference = ErrorReference::isValid($reference) ? $reference : ErrorReference::generate();

        if (defined('WP_AWARE_ERRORS_HISTORY') && ! Gate::toBool(WP_AWARE_ERRORS_HISTORY)) {
            self::logReference($reference);
            return;
        }
        if (! function_exists('get_option') || ! function_exists('update_option')) {
            return;
        }

        try {
            self::migrateLegacy();

            $history = get_option(self::OPTION, []);
            if (! is_array($history)) {
                $history = [];
            }
            $cleanHistory = [];
            foreach ($history as $row) {
                if (is_array($row)) {
                    $cleanHistory[] = self::sanitiseRecord($row);
                }
            }
            $history = $cleanHistory;

            $source = (array) ($context['likely_source'] ?? []);
            $request = (array) ($context['request'] ?? []);
            $history[] = self::sanitiseRecord([
                'reference' => $reference,
                'time' => $error->time,
                'type' => $error->type,
                'message' => $error->message,
                'file' => FrameClassifier::relative($error->file),
                'line' => $error->line,
                'source' => (string) ($source['label'] ?? 'Unknown'),
                'source_kind' => (string) ($source['kind'] ?? 'unknown'),
                'method' => (string) ($request['method'] ?? ''),
                'uri' => $request['uri'] ?? '',
                'ajax_action' => $request['ajax_action'] ?? '',
                'rest_route' => $request['rest_route'] ?? '',
            ]);

            $limit = defined('WP_AWARE_ERRORS_HISTORY_LIMIT') ? min(1000, max(1, (int) WP_AWARE_ERRORS_HISTORY_LIMIT)) : 30;
            update_option(self::OPTION, array_slice($history, -$limit), false);
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        } catch (Throwable) {
            self::logReference($reference);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        self::migrateLegacy();

        $history = get_option(self::OPTION, []);
        if (! is_array($history)) {
            return [];
        }

        $clean = [];
        foreach ($history as $row) {
            if (is_array($row)) {
                $clean[] = self::sanitiseRecord($row);
            }
        }

        return array_values(array_reverse($clean));
    }

    public static function clear(): void
    {
        if (! function_exists('delete_option')) {
            return;
        }

        delete_option(self::OPTION);
        delete_option(self::QUARANTINE_OPTION);
        delete_option(self::LEGACY_NOTICE_OPTION);
        delete_option(self::EARLY_OPTION);
        if (function_exists('update_option')) {
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        }
    }

    public static function clearLegacy(): void
    {
        if (! function_exists('get_option') || ! function_exists('update_option')) {
            return;
        }

        try {
            $history = get_option(self::OPTION, []);
            $kept = [];
            if (is_array($history)) {
                foreach ($history as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $clean = self::sanitiseRecord($row);
                    // New records carry an opaque reference; legacy records do not.
                    if ($clean['reference'] !== '') {
                        $kept[] = $clean;
                    }
                }
            }

            $limit = defined('WP_AWARE_ERRORS_HISTORY_LIMIT') ? min(1000, max(1, (int) WP_AWARE_ERRORS_HISTORY_LIMIT)) : 30;
            update_option(self::OPTION, array_slice($kept, -$limit), false);
            if (function_exists('delete_option')) {
                delete_option(self::QUARANTINE_OPTION);
                delete_option(self::LEGACY_NOTICE_OPTION);
            }
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        } catch (Throwable) {
        }
    }

    public static function hasLegacyNotice(): bool
    {
        return function_exists('get_option') && (bool) get_option(self::LEGACY_NOTICE_OPTION, false);
    }

    public static function hasQuarantinedRecords(): bool
    {
        return self::quarantinedCount() > 0;
    }

    public static function quarantinedCount(): int
    {
        if (! function_exists('get_option')) {
            return 0;
        }

        $value = get_option(self::QUARANTINE_OPTION, []);
        if (! is_array($value)) {
            return 0;
        }

        return max(0, (int) ($value['count'] ?? 0));
    }

    /**
     * Minimal, non-sensitive reference store for fatal errors that happen before
     * the full plugin can load and therefore cannot write a normal history row.
     */
    public static function addEarlyReference(string $reference): void
    {
        if (! ErrorReference::isValid($reference) || ! function_exists('get_option') || ! function_exists('update_option')) {
            return;
        }

        try {
            $references = get_option(self::EARLY_OPTION, []);
            if (! is_array($references)) {
                $references = [];
            }

            $references[] = [
                'reference' => $reference,
                'time' => gmdate('c'),
            ];

            $clean = [];
            foreach (array_slice($references, -10) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $ref = (string) ($row['reference'] ?? '');
                if (ErrorReference::isValid($ref)) {
                    $clean[] = [
                        'reference' => $ref,
                        'time' => is_scalar($row['time'] ?? null) ? (string) $row['time'] : '',
                    ];
                }
            }

            update_option(self::EARLY_OPTION, $clean, false);
        } catch (Throwable) {
        }
    }

    /** @return list<array{reference:string,time:string}> */
    public static function earlyReferences(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $references = get_option(self::EARLY_OPTION, []);
        if (! is_array($references)) {
            return [];
        }

        $clean = [];
        foreach ($references as $row) {
            if (! is_array($row)) {
                continue;
            }
            $reference = (string) ($row['reference'] ?? '');
            if (ErrorReference::isValid($reference)) {
                $clean[] = [
                    'reference' => $reference,
                    'time' => is_scalar($row['time'] ?? null) ? (string) $row['time'] : '',
                ];
            }
        }

        return array_values(array_reverse($clean));
    }

    public static function hasEarlyReferences(): bool
    {
        return self::earlyReferences() !== [];
    }

    /**
     * One-time migration of pre-redaction records. Known fields are sanitised;
     * unknown fields are discarded. Records that cannot be read as arrays are
     * never displayed, and only non-sensitive count/time metadata is retained.
     */
    public static function migrateLegacy(): void
    {
        if (! function_exists('get_option') || ! function_exists('update_option')) {
            return;
        }

        try {
            $schema = (int) get_option(self::SCHEMA_OPTION, 0);
            if ($schema >= self::SCHEMA_VERSION) {
                return;
            }

            $raw = get_option(self::OPTION, []);
            $clean = [];
            $quarantined = 0;

            if (is_array($raw)) {
                foreach ($raw as $row) {
                    if (is_array($row)) {
                        $clean[] = self::sanitiseRecord($row);
                    } else {
                        $quarantined++;
                    }
                }
            } elseif ($raw !== false && $raw !== null && $raw !== '') {
                $quarantined++;
            }

            $limit = defined('WP_AWARE_ERRORS_HISTORY_LIMIT') ? min(1000, max(1, (int) WP_AWARE_ERRORS_HISTORY_LIMIT)) : 30;
            update_option(self::OPTION, array_slice($clean, -$limit), false);
            if ($quarantined > 0) {
                // Store only non-sensitive metadata. Raw unreadable rows are discarded.
                update_option(self::QUARANTINE_OPTION, [
                    'count' => $quarantined,
                    'time' => gmdate('c'),
                ], false);
            }
            if ($raw !== [] && $raw !== false && $raw !== null && $raw !== '') {
                update_option(self::LEGACY_NOTICE_OPTION, 1, false);
            }
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        } catch (Throwable) {
            // Leave the schema flag unset so a later request can retry safely.
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function sanitiseRecord(array $row): array
    {
        $reference = self::stringValue($row['reference'] ?? '');

        return [
            'reference' => ErrorReference::isValid($reference) ? $reference : '',
            'time' => self::sanitisedString('time', $row['time'] ?? ''),
            'type' => self::sanitisedString('type', $row['type'] ?? ''),
            'message' => self::sanitisedString('message', $row['message'] ?? ''),
            'file' => self::sanitisedString('file', $row['file'] ?? ''),
            'line' => is_scalar($row['line'] ?? null) ? (int) $row['line'] : 0,
            'source' => self::sanitisedString('source', $row['source'] ?? ''),
            'source_kind' => self::sanitisedString('source_kind', $row['source_kind'] ?? ''),
            'method' => self::sanitisedString('method', $row['method'] ?? ''),
            'uri' => self::sanitisedString('uri', $row['uri'] ?? ''),
            'ajax_action' => self::sanitisedString('action', $row['ajax_action'] ?? ''),
            'rest_route' => self::sanitisedString('route', $row['rest_route'] ?? ''),
        ];
    }

    private static function sanitisedString(string $key, mixed $value): string
    {
        $sanitised = Sanitizer::value($key, $value);

        return is_scalar($sanitised) ? (string) $sanitised : '';
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function logReference(string $reference): void
    {
        if (function_exists('error_log')) {
            error_log('WP Aware Errors reference: ' . $reference);
        }
    }
}
