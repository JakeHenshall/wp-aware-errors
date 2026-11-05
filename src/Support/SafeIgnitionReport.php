<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Support;

use Hensh\WpAwareErrors\Sanitizer;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Context\ContextProvider;
use Spatie\FlareClient\Report;
use Throwable;

/**
 * Builds the structured report supplied to Ignition.
 *
 * Ignition renders its own report JSON and source comments. We deliberately
 * replace the report/throwable it receives so frame arguments, source snippets,
 * cookies, sessions, headers and raw exception messages cannot bypass the
 * plugin's redaction layer.
 */
final class SafeIgnitionReport
{
    /** @return array{0:\Throwable,1:Report} */
    public static function create(Throwable $throwable, ?array $context = null, string $applicationPath = ''): array
    {
        $message = (string) Sanitizer::value('message', $throwable->getMessage());

        // A sanitised throwable keeps Ignition's comment/noscript/title output safe.
        // Its trace is intentionally short and argument-free; the sanitised report
        // supplies the display stack.
        $safeThrowable = new \ErrorException(
            $message,
            0,
            E_ERROR,
            $throwable->getFile(),
            $throwable->getLine()
        );

        $frames = [];
        foreach (Backtrace::createForThrowable($throwable)->withArguments(false)->frames() as $frame) {
            $frames[] = new SanitizedFrame(
                (string) $frame->file,
                (int) $frame->lineNumber,
                null,
                $frame->method,
                $frame->class,
                null,
                (bool) $frame->applicationFrame
            );
        }

        $backtrace = new SanitizedBacktrace($frames);
        $report = (new Report())
            ->exceptionClass($throwable::class)
            ->message($message)
            ->useContext(new SanitizedContextProvider($context ?? []))
            ->stacktrace($backtrace)
            ->throwable($safeThrowable)
            ->handled(true)
            ->notifierName('WP Aware Errors')
            ->languageVersion(PHP_VERSION);

        if ($applicationPath !== '') {
            $report->setApplicationPath($applicationPath);
        }
        if (defined('WP_AWARE_ERRORS_VERSION')) {
            $report->setApplicationVersion((string) WP_AWARE_ERRORS_VERSION);
        }

        $report->openFrameIndex($backtrace->firstApplicationFrameIndex());

        return [$safeThrowable, $report];
    }
}

final class SanitizedFrame extends Frame
{
    public function getSnippet(int $lineCount): array
    {
        return [];
    }

    public function getSnippetAsString(int $lineCount): string
    {
        return '';
    }

    public function getSnippetProperties(int $lineCount): array
    {
        return [];
    }
}

final class SanitizedBacktrace extends Backtrace
{
    /** @param list<Frame> $frames */
    public function __construct(private array $frames) {}

    /** @return list<Frame> */
    public function frames(): array
    {
        return $this->frames;
    }
}

final class SanitizedContextProvider implements ContextProvider
{
    /** @param array<string,mixed> $context */
    public function __construct(private array $context) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $request = (array) ($this->context['request'] ?? []);
        $headers = (array) ($request['headers'] ?? []);
        $safeHeaders = Sanitizer::value('headers', $headers);
        if (! is_array($safeHeaders)) {
            $safeHeaders = [];
        }

        return [
            'request' => [
                'url' => (string) Sanitizer::value('uri', $request['uri'] ?? ''),
                'ip' => null,
                'method' => (string) ($request['method'] ?? ''),
                'useragent' => (string) Sanitizer::value('user-agent', $headers['user-agent'] ?? ''),
            ],
            'request_data' => [
                'queryString' => Sanitizer::value('query', $request['query'] ?? []),
                'body' => Sanitizer::value('post', $request['post'] ?? []),
                'files' => [],
            ],
            'headers' => $safeHeaders,
            'cookies' => [],
            'session' => [],
        ];
    }
}
