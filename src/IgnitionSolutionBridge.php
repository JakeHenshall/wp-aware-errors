<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Spatie\ErrorSolutions\Contracts\BaseSolution;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\ErrorSolutions\Contracts\Solution;
use Throwable;

/** Adapts this plugin's solution providers to the solutions Ignition renders. */
final class IgnitionSolutionBridge implements HasSolutionsForThrowable
{
    /** @var array<int,list<array{title:string,description:string,action:string}>> */
    private array $cache = [];
    /** @var array<int,array<string,mixed>> */
    private array $contexts = [];

    /** @param array<string,mixed>|null $context */
    public function __construct(private ?array $context = null) {}

    public function canSolve(Throwable $throwable): bool
    {
        return $this->getSolutions($throwable) !== [];
    }

    /** @return array<int, Solution> */
    public function getSolutions(Throwable $throwable): array
    {
        $id = spl_object_id($throwable);
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        try {
            $error = ExceptionData::fromThrowable($throwable);
            $context = $this->context
                ?? ($this->contexts[$id] ??= ContextCollector::collect($error));
            $solutions = [];
            foreach (SolutionManager::solutions($error, $context) as $solution) {
                $title = (string) Sanitizer::text((string) ($solution['title'] ?? ''));
                $description = (string) Sanitizer::text((string) ($solution['description'] ?? ''));
                $action = (string) Sanitizer::text((string) ($solution['action'] ?? ''));
                $solutions[] = BaseSolution::create($title)
                    ->setSolutionDescription(trim($description . ' ' . $action));
            }

            return $this->cache[$id] = $solutions;
        } catch (Throwable) {
            return $this->cache[$id] = [];
        }
    }
}
