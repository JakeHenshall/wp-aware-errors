<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class ComponentOwnership
{
    /** @return list<array<string,mixed>> */
    public static function callbacksForHooks(array $hooks, int $limit = 20): array
    {
        global $wp_filter;
        $result = [];
        foreach (array_values(array_unique(array_reverse($hooks))) as $hook) {
            if (! isset($wp_filter[$hook]) || ! is_object($wp_filter[$hook]) || ! isset($wp_filter[$hook]->callbacks)) continue;
            foreach ((array) $wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ((array) $callbacks as $callback) {
                    $function = $callback['function'] ?? null;
                    $owner = self::ownerOfCallable($function);
                    $result[] = [
                        'hook' => (string) $hook,
                        'priority' => (int) $priority,
                        'callback' => self::callableName($function),
                        'file' => $owner['file'],
                        'relative' => FrameClassifier::relative((string) $owner['file']),
                        'component' => $owner['component'],
                        'kind' => $owner['kind'],
                    ];
                    if (count($result) >= $limit) return $result;
                }
            }
        }
        return $result;
    }

    /** @return array{file:string,component:string,kind:string} */
    private static function ownerOfCallable(mixed $callable): array
    {
        try {
            $reflection = null;
            if (is_array($callable) && count($callable) === 2) {
                $class = is_object($callable[0]) ? $callable[0]::class : (string) $callable[0];
                $reflection = new ReflectionMethod($class, (string) $callable[1]);
            } elseif (is_string($callable) && str_contains($callable, '::')) {
                [$class, $method] = explode('::', $callable, 2);
                $reflection = new ReflectionMethod($class, $method);
            } elseif ($callable instanceof \Closure || is_string($callable)) {
                $reflection = new ReflectionFunction($callable);
            } elseif (is_object($callable) && method_exists($callable, '__invoke')) {
                $reflection = new ReflectionMethod($callable, '__invoke');
            }
            $file = $reflection ? (string) $reflection->getFileName() : '';
            $class = FrameClassifier::classify($file);
            return ['file' => $file, 'component' => (string) ($class['label'] ?? 'Runtime'), 'kind' => (string) ($class['kind'] ?? 'runtime')];
        } catch (Throwable) {
            return ['file' => '', 'component' => 'Runtime/unknown', 'kind' => 'runtime'];
        }
    }

    private static function callableName(mixed $callable): string
    {
        if (is_string($callable)) return $callable;
        if ($callable instanceof \Closure) return 'Closure';
        if (is_array($callable) && count($callable) === 2) {
            $left = is_object($callable[0]) ? $callable[0]::class : (string) $callable[0];
            return $left . '::' . (string) $callable[1];
        }
        if (is_object($callable)) return $callable::class . '::__invoke';
        return 'Unknown callback';
    }
}
