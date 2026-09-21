<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class ParseProvider extends MessageSolutionProvider
{
    protected function needles(): array { return ['syntax error', 'parse error', 'unexpected token']; }
    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'PHP parser failure',
            'description' => 'The parser can report the line after the actual typo.',
            'action' => 'Run php -l on the file and inspect several lines before the highlighted location.',
        ]];
    }
}
