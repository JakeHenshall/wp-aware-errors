<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class SolutionManager
{
    /** @var list<SolutionProvider>|null */
    private static ?array $providers = null;

    /** @return list<array{title:string,description:string,action:string}> */
    public static function solutions(ExceptionData $error, array $context): array
    {
        if (self::$providers === null) {
            self::$providers = [
                new CompatibilitySolutionProvider(),
                new WooCommerceSolutionProvider(),
                new MissingFunctionProvider(),
                new MissingClassProvider(),
                new UndefinedMethodProvider(),
                new MemoryProvider(),
                new ParseProvider(),
                new HeadersProvider(),
            ];
            if (function_exists('apply_filters')) {
                $filtered = apply_filters('wp_aware_errors_solution_providers', self::$providers);
                if (is_array($filtered)) self::$providers = $filtered;
            }
        }

        $all = [];
        foreach (self::$providers as $provider) {
            try {
                if ($provider instanceof SolutionProvider && $provider->supports($error, $context)) {
                    foreach ($provider->provide($error, $context) as $solution) $all[] = $solution;
                }
            } catch (Throwable) {}
        }
        return array_slice($all, 0, 8);
    }
}
