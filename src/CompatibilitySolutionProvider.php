<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class CompatibilitySolutionProvider implements SolutionProvider
{
    public function supports(ExceptionData $error, array $context): bool
    {
        return ! empty($context['compatibility']['issues']);
    }
    public function provide(ExceptionData $error, array $context): array
    {
        $solutions = [];
        foreach (array_slice((array) ($context['compatibility']['issues'] ?? []), 0, 4) as $issue) {
            $solutions[] = [
                'title' => (($issue['severity'] ?? '') === 'error' ? 'Dependency requirement failed' : 'Compatibility warning'),
                'description' => (string) ($issue['message'] ?? ''),
                'action' => 'Align the dependency/runtime versions or verify the extension against the installed version before debugging deeper.',
            ];
        }
        return $solutions;
    }
}
