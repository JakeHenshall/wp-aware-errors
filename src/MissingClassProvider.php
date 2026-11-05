<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class MissingClassProvider implements SolutionProvider
{
    public function supports(ExceptionData $error, array $context): bool
    {
        $message = strtolower($error->message);
        return str_contains($message, 'class') && str_contains($message, 'not found');
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
