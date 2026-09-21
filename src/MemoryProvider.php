<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class MemoryProvider extends MessageSolutionProvider
{
    protected function needles(): array { return ['allowed memory size', 'memory exhausted']; }
    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'Memory exhausted',
            'description' => 'Increasing the limit can hide the real cause when a loop, unbounded query, image operation or recursive hook is responsible.',
            'action' => 'Inspect the recent hook trail and queries first; then profile the failing operation before raising WP_MEMORY_LIMIT.',
        ]];
    }
}
