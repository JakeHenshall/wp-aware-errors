<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

abstract class MessageSolutionProvider implements SolutionProvider
{
    /** @return list<string> */
    abstract protected function needles(): array;
    public function supports(ExceptionData $error, array $context): bool
    {
        $message = strtolower($error->message);
        foreach ($this->needles() as $needle) if (str_contains($message, $needle)) return true;
        return false;
    }
}
