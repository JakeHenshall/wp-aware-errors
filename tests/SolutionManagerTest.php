<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ExceptionData;
use Hensh\WpAwareErrors\SolutionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SolutionManagerTest extends TestCase
{
    public function testMatchesEachHighConfidenceProvider(): void
    {
        $cases = [
            'Call to undefined function foo()' => 'Function is unavailable at this point in the WordPress lifecycle',
            'Class "Foo" not found' => 'Class/autoload dependency is missing',
            'Call to a member function get_price() on null' => 'A value was null where an object was expected',
            "require(/x): Failed opening required '/x'" => 'Required file is missing',
            'Call to undefined method Foo::bar()' => 'Possible API/version mismatch',
            'Allowed memory size of 134217728 bytes exhausted' => 'Memory exhausted',
            'Cannot modify header information - headers already sent' => 'Output occurred before WordPress sent headers',
            'syntax error, unexpected token "}"' => 'PHP parser failure',
        ];

        foreach ($cases as $message => $expectedTitle) {
            self::assertContains($expectedTitle, $this->titles($message), $message);
        }
    }

    public function testCompatibilityIssuesBecomeSolutions(): void
    {
        $titles = $this->titles('Anything', [
            'compatibility' => [
                'issues' => [[
                    'severity' => 'error',
                    'message' => 'Requires PHP 8.3+',
                ]],
            ],
        ]);

        self::assertContains('Dependency requirement failed', $titles);
    }

    public function testWooCommerceContextBecomeASolution(): void
    {
        $titles = $this->titles('WooCommerce checkout failed', [
            'woocommerce' => [
                'active' => true,
                'version' => '9.0',
                'ajax_endpoint' => 'checkout',
                'hpos' => true,
            ],
        ]);

        self::assertContains('WooCommerce request context captured', $titles);
    }

    public function testReturnsNoSolutionsForUnmatchedMessages(): void
    {
        self::assertSame([], $this->titles('Something completely different'));
    }

    /** @param array<string,mixed> $overrides @return list<string> */
    private function titles(string $message, array $overrides = []): array
    {
        $context = array_replace_recursive([
            'likely_source' => ['kind' => 'app'],
            'compatibility' => ['issues' => []],
            'woocommerce' => ['active' => false],
        ], $overrides);

        $solutions = SolutionManager::solutions(ExceptionData::fromThrowable(new RuntimeException($message)), $context);

        return array_column($solutions, 'title');
    }
}
