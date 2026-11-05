<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\Sanitizer;
use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    public function testPsr4ClassesLoadAndTraversalIsRejected(): void
    {
        require_once dirname(__DIR__) . '/autoload.php';

        self::assertTrue(class_exists(Sanitizer::class));
        self::assertFalse(class_exists('Hensh\\WpAwareErrors\\..\\..\\etc\\passwd'));
        self::assertFalse(class_exists('Hensh\\WpAwareErrors\\Not/A/Class'));
    }
}
