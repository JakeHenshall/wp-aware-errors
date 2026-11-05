<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;


interface SolutionProvider
{
    public function supports(ExceptionData $error, array $context): bool;
    /** @return list<array{title:string,description:string,action:string}> */
    public function provide(ExceptionData $error, array $context): array;
}
