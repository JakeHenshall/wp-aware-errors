<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class ErrorHandler
{
    /** @var null|callable */
    private static $previousExceptionHandler = null;

    public static function register(): void
    {
        self::$previousExceptionHandler = set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $exception): void
    {
        if (! Gate::enabled() || Runtime::rendered()) {
            self::delegate($exception);
            return;
        }

        Runtime::markRendered();
        $data = ExceptionData::fromThrowable($exception);
        Renderer::render($data);
        exit(1);
    }

    public static function handleShutdown(): void
    {
        if (! Gate::enabled() || Runtime::rendered()) {
            return;
        }

        $error = error_get_last();
        if (! is_array($error) || ! self::isFatal((int) ($error['type'] ?? 0))) {
            return;
        }

        $message = (string) ($error['message'] ?? '');
        if (preg_match('/allowed memory size|memory exhausted/i', $message)) {
            self::giveHeadroom();
        }

        Runtime::markRendered();
        Renderer::render(ExceptionData::fromPhpError($error));
    }

    private static function giveHeadroom(): void
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return;
        }
        $unit = strtolower(substr($raw, -1));
        $n = (float) $raw;
        $bytes = (int) match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => (int) $raw,
        };
        if ($bytes > 0) {
            @ini_set('memory_limit', (string) ($bytes + 32 * 1024 * 1024));
        }
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
        error_log((string) $exception);
    }
}
