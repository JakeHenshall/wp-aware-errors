<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\HookRecorder;
use Hensh\WpAwareErrors\PluginThemeIndex;
use Hensh\WpAwareErrors\Runtime;
use ReflectionClass;

final class State
{
    public static function resetPluginIndex(): void
    {
        self::setStatic(PluginThemeIndex::class, [
            'plugins' => [],
            'themes' => [],
            'primed' => false,
            'themesPrimed' => false,
        ]);
    }

    public static function resetHookRecorder(): void
    {
        self::setStatic(HookRecorder::class, [
            'history' => [],
            'limit' => 50,
            'recording' => false,
            'captureArgs' => true,
            'stopped' => false,
            'operations' => 0,
        ]);
    }

    public static function resetRuntime(): void
    {
        self::setStatic(Runtime::class, [
            'installation' => null,
            'rendered' => false,
            'handling' => false,
            'wordPressFatalOutput' => false,
            'requestContext' => [],
        ]);
    }

    /** @param array<string,mixed> $values */
    private static function setStatic(string $class, array $values): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($values as $name => $value) {
            $reflection->getProperty($name)->setValue(null, $value);
        }
    }
}
