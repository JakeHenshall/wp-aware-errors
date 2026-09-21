<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class HeadersProvider extends MessageSolutionProvider
{
    protected function needles(): array { return ['headers already sent']; }
    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'Output occurred before WordPress sent headers',
            'description' => 'Whitespace/BOM, var_dump/echo output or template rendering may have started before redirects/cookies.',
            'action' => 'Trace the first output location and remove accidental output before wp_redirect(), cookies or session headers.',
        ]];
    }
}
