<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class NullCallProvider implements SolutionProvider
{
    public function supports(ExceptionData $error, array $context): bool
    {
        $message = strtolower($error->message);
        if (str_contains($message, 'of non-object')) {
            return true;
        }
        if (! str_contains($message, 'on null')) {
            return false;
        }

        return str_contains($message, 'member function') || str_contains($message, 'property');
    }

    public function provide(ExceptionData $error, array $context): array
    {
        return [[
            'title' => 'A value was null where an object was expected',
            'description' => 'This is a common cause of a blank white page. A template or hook expected an object such as a product, post, or user, and received null.',
            'action' => 'Guard the call before using it, and confirm the current hook runs only after that object exists.',
        ]];
    }
}
