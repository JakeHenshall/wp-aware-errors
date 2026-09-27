<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class MissingFileProvider extends MessageSolutionProvider
{
    protected function needles(): array
    {
        return ['failed opening required', 'failed to open stream'];
    }

    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'Required file is missing',
            'description' => 'A require or include points at a path that is not on disk. This is commonly a missing Composer install, a renamed plugin directory, or a theme file that was not deployed.',
            'action' => 'Confirm the path exists, run composer install when it lives under vendor/, and do not require files before the owning plugin or theme is loaded.',
        ]];
    }
}
