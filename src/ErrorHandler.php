<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

final class ErrorHandler
{
    /** @var null|callable */
    private static $previousExceptionHandler = null;

    /**
     * WordPress selects a wp_die() handler based on the request format. All of
     * them are wrapped so a fatal error reaches the single ErrorPresenter path
     * with the correct protocol response and access policy.
     *
     * @var list<string>
     */
    private const WP_DIE_HANDLERS = [
        'wp_die_handler',
        'wp_die_ajax_handler',
        'wp_die_json_handler',
        'wp_die_jsonp_handler',
        'wp_die_xml_handler',
        'wp_die_xmlrpc_handler',
    ];

    public static function register(): void
    {
        self::$previousExceptionHandler = set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        // WordPress's fatal handler runs before this plugin's shutdown function.
        // Intercept its wp_die() call so the response is rendered once, not twice.
        if (function_exists('add_filter')) {
            foreach (self::WP_DIE_HANDLERS as $hook) {
                add_filter($hook, [self::class, 'filterWpDieHandler'], PHP_INT_MAX);
            }

            // If another plugin replaces a wp_die handler later, this marker lets
            // the shutdown fallback avoid appending a second error page.
            add_filter('wp_php_error_message', [self::class, 'markWordPressFatalOutput'], PHP_INT_MIN);
        }
    }

    public static function handleException(Throwable $exception): void
    {
        if (Runtime::rendered() || Runtime::handling()) {
            self::delegate($exception);
            return;
        }

        self::present($exception);
        exit(1);
    }

    public static function handleShutdown(): void
    {
        if (! Gate::handlingEnabled() || Runtime::rendered()) {
            return;
        }
        if (defined('WP_AWARE_ERRORS_FATAL_RENDERED') && WP_AWARE_ERRORS_FATAL_RENDERED) {
            return;
        }
        if (Runtime::wordPressFatalOutput()) {
            return;
        }

        $error = error_get_last();
        $fatal = is_array($error) && self::isFatal((int) ($error['type'] ?? 0));

        // A fatal error occurred while the presenter was collecting diagnostics
        // or writing history. Emit a minimal generic page without re-entering the
        // full presenter or touching history again.
        if (Runtime::handling()) {
            if ($fatal) {
                if (preg_match('/allowed memory size|memory exhausted/i', (string) ($error['message'] ?? ''))) {
                    self::giveHeadroom();
                }
                self::presentMinimal();
            }
            return;
        }

        if (! $fatal) {
            return;
        }

        $message = (string) ($error['message'] ?? '');
        if (preg_match('/allowed memory size|memory exhausted/i', $message)) {
            self::giveHeadroom();
        }

        self::present(self::exceptionFromError($error));
    }

    /**
     * Wraps every wp_die() handler. Only WordPress fatal errors are taken over;
     * ordinary wp_die() calls (nonces, permissions) delegate unchanged.
     */
    public static function filterWpDieHandler(mixed $handler): mixed
    {
        if (! is_callable($handler)) {
            return $handler;
        }

        return static function (mixed $message, mixed $title = '', mixed $args = []) use ($handler): mixed {
            if (! Runtime::rendered() && Gate::handlingEnabled()) {
                $error = self::fatalFromWpError($message);
                if ($error !== null) {
                    Runtime::clearWordPressFatalOutput();

                    if (Runtime::handling()) {
                        self::presentMinimal();
                    } else {
                        self::present(self::exceptionFromError($error));
                    }

                    exit(1);
                }
            }

            return call_user_func($handler, $message, $title, $args);
        };
    }

    /**
     * Marks that WordPress supplied a fatal-error template. If our wp_die wrapper
     * is reached, it clears this marker and takes over; otherwise the shutdown
     * handler stays out of the way and avoids duplicate output.
     */
    public static function markWordPressFatalOutput(mixed $message): mixed
    {
        Runtime::markWordPressFatalOutput();

        return $message;
    }

    private static function present(Throwable $exception): void
    {
        try {
            ErrorPresenter::render($exception);
        } catch (Throwable) {
            self::presentMinimal();
        }
    }

    /**
     * Last-resort safe response. It never includes exception details and avoids
     * all diagnostic collection.
     */
    private static function presentMinimal(): void
    {
        if (Runtime::rendered()) {
            return;
        }

        Runtime::markRendered();

        try {
            $kind = RequestKind::detect();
        } catch (Throwable) {
            $kind = RequestKind::HTML;
        }

        $reference = ErrorReference::generate();
        if (function_exists('error_log')) {
            error_log('WP Aware Errors minimal reference: ' . $reference);
        }

        try {
            ErrorResponse::send(ErrorResponse::generic($kind, $reference), $kind);
        } catch (Throwable) {
            // Plain HTML is the final fallback.
            if (! headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Something went wrong</title></head><body><h1>Something went wrong</h1><p>Reference: <code>'
                . htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</code></p></body></html>';
        }
    }

    /** @return array<string,mixed>|null */
    private static function fatalFromWpError(mixed $message): ?array
    {
        if (! is_object($message) || ! is_a($message, 'WP_Error')) {
            return null;
        }
        if ($message->get_error_code() !== 'internal_server_error') {
            return null;
        }

        $data = $message->get_error_data();
        $error = is_array($data) ? ($data['error'] ?? null) : null;
        if (! is_array($error) || ! self::isFatal((int) ($error['type'] ?? 0))) {
            return null;
        }

        return $error;
    }

    /** @param array<string,mixed> $error */
    private static function exceptionFromError(array $error): \ErrorException
    {
        return new \ErrorException(
            (string) ($error['message'] ?? 'Unknown fatal error'),
            0,
            (int) ($error['type'] ?? E_ERROR),
            (string) ($error['file'] ?? ''),
            (int) ($error['line'] ?? 0)
        );
    }

    private static function giveHeadroom(): void
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1' || ! preg_match('/^(\d+(?:\.\d+)?)\s*([kmg])?/i', $raw, $matches)) {
            return;
        }
        $bytes = self::bytes((float) $matches[1], strtolower($matches[2] ?? ''));
        if ($bytes > 0) {
            @ini_set('memory_limit', (string) ($bytes + 32 * 1024 * 1024));
        }
    }

    private static function bytes(float $value, string $unit): int
    {
        return (int) match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
    }

    private static function delegate(Throwable $exception): void
    {
        if (is_callable(self::$previousExceptionHandler)) {
            call_user_func(self::$previousExceptionHandler, $exception);
            return;
        }

        // Never write raw messages/traces to logs; they may contain secrets.
        error_log(sprintf(
            'WP Aware Errors: unhandled %s at %s:%d',
            $exception::class,
            FrameClassifier::relative($exception->getFile()),
            $exception->getLine()
        ));
    }
}
