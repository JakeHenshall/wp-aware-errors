<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class UndefinedMethodProvider extends MessageSolutionProvider
{
    protected function needles(): array
    {
        return ['undefined method'];
    }

    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'Possible API/version mismatch',
            'description' => 'An undefined method often means code expects a different version of WordPress, WooCommerce or another plugin.',
            'action' => 'Compare the owning component version with the integration’s declared requirements and changelog.',
        ]];
    }
}
