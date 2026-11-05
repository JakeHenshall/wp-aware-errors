<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ErrorReference;
use PHPUnit\Framework\TestCase;

final class ErrorReferenceTest extends TestCase
{
    public function testGeneratesOpaquePrefixedReference(): void
    {
        $reference = ErrorReference::generate();

        self::assertMatchesRegularExpression('/^WAE-[A-F0-9]{16}$/', $reference);
        self::assertTrue(ErrorReference::isValid($reference));
        self::assertNotSame($reference, ErrorReference::generate());
    }

    public function testRejectsMalformedReferences(): void
    {
        self::assertFalse(ErrorReference::isValid(''));
        self::assertFalse(ErrorReference::isValid('WAE-123'));
        self::assertFalse(ErrorReference::isValid('../../WAE-1234567890ABCDEF'));
        self::assertFalse(ErrorReference::isValid('WAE-1234567890ABCDEF<script>'));
    }
}
