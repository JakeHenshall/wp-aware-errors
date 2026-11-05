<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

final class ExceptionData
{
    /** @param list<array<string,mixed>> $trace */
    private function __construct(
        public string $type,
        public string $message,
        public string $file,
        public int $line,
        public array $trace,
        public string $time
    ) {}

    public static function fromThrowable(Throwable $exception): self
    {
        $trace = array_slice($exception->getTrace(), 0, 100, true);
        array_unshift($trace, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => '{throw}',
        ]);

        return new self(
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $trace,
            gmdate('c')
        );
    }

    public function isMemoryExhausted(): bool
    {
        return (bool) preg_match('/allowed memory size|memory exhausted/i', $this->message);
    }
}
