<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class MissingFunctionProvider extends MessageSolutionProvider
{
    protected function needles(): array { return ['undefined function', 'call to undefined function']; }
    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'Function is unavailable at this point in the WordPress lifecycle',
            'description' => 'The defining plugin/file may not be loaded yet, or a Composer autoloader/dependency may be missing.',
            'action' => 'Check the current hook, dependency activation, namespaces and autoloader before moving the call later in the lifecycle.',
        ]];
    }
}
