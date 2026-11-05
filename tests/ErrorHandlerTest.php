<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ErrorHandler;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WP_Error;

final class ErrorHandlerTest extends TestCase
{
    public function testNonCallableDieHandlersAreReturnedUnchanged(): void
    {
        self::assertSame('not-callable', ErrorHandler::filterWpDieHandler('not-callable'));
    }

    public function testNonFatalWpDieCallsDelegateToThePreviousHandler(): void
    {
        $called = false;
        $wrapper = ErrorHandler::filterWpDieHandler(
            static function (mixed $message, string $title = '', array $args = []) use (&$called): string {
                $called = true;
                return 'delegated';
            }
        );

        self::assertIsCallable($wrapper);
        self::assertSame('delegated', $wrapper('Not allowed.', '', []));
        self::assertTrue($called);
    }

    public function testExtractsOnlyWordPressFatalErrorsFromWpError(): void
    {
        $method = new ReflectionMethod(ErrorHandler::class, 'fatalFromWpError');

        $fatal = new WP_Error('internal_server_error', 'x', [
            'error' => ['type' => E_ERROR, 'message' => 'boom', 'file' => '/x.php', 'line' => 12],
        ]);
        $error = $method->invoke(null, $fatal);

        self::assertSame('boom', $error['message'] ?? null);
        self::assertSame(12, $error['line'] ?? null);
        self::assertNull($method->invoke(null, new WP_Error('internal_server_error', 'x', ['error' => ['type' => E_WARNING]])));
        self::assertNull($method->invoke(null, new WP_Error('other_error', 'x', ['error' => ['type' => E_ERROR]])));
        self::assertNull($method->invoke(null, 'not an error'));
    }

}
