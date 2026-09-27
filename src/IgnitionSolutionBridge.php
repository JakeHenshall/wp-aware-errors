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
    public function canSolve(Throwable $throwable): bool
    {
        return $this->getSolutions($throwable) !== [];
    }

    /** @return array<int, Solution> */
    public function getSolutions(Throwable $throwable): array
    {
        try {
            $error = ExceptionData::fromThrowable($throwable);
            $context = ContextCollector::collect($error);
            $solutions = [];
            foreach (SolutionManager::solutions($error, $context) as $solution) {
                $solutions[] = BaseSolution::create($solution['title'])
                    ->setSolutionDescription(trim($solution['description'] . ' ' . $solution['action']));
            }

            return $solutions;
        } catch (Throwable) {
            return [];
        }
    }
}
