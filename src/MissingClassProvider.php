<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class MissingClassProvider extends MessageSolutionProvider
{
    protected function needles(): array { return ['class "', 'class \'', 'not found']; }
    public function supports(ExceptionData $error, array $context): bool
    {
        $m = strtolower($error->message);
        return str_contains($m, 'class') && str_contains($m, 'not found');
    }
    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'Class/autoload dependency is missing',
            'description' => 'This is commonly a namespace typo, unloaded Composer autoloader, or integration running while its dependency plugin is inactive.',
            'action' => 'Verify use/import statements, vendor/autoload.php and guard cross-plugin integrations with class_exists().',
        ]];
    }
}
