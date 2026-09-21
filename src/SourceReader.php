<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class SourceReader
{
    /** @return list<array{line:int,text:string,active:bool}> */
    public static function around(string $file, int $line, int $radius = 9): array
    {
        if ($file === '' || ! is_readable($file) || $line < 1) return [];
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) return [];
        $start = max(1, $line - $radius);
        $end = min(count($lines), $line + $radius);
        $result = [];
        for ($i = $start; $i <= $end; $i++) {
            $result[] = ['line' => $i, 'text' => (string) ($lines[$i - 1] ?? ''), 'active' => $i === $line];
        }
        return $result;
    }
}
