<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests\Integration;

use Hensh\WpAwareErrors\ErrorHistory;
use ReflectionMethod;
use WP_UnitTestCase;

final class FatalHandlerIntegrationTest extends WP_UnitTestCase
{
    private object $handler;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WP_Fatal_Error_Handler')) {
            require_once ABSPATH . WPINC . '/class-wp-fatal-error-handler.php';
        }

        if (! class_exists('WP_Aware_Errors_Fatal_Handler')) {
            $GLOBALS['wae_fatal_handler'] = require dirname(__DIR__, 2) . '/dropins/fatal-error-handler.php';
        }
        $this->handler = $GLOBALS['wae_fatal_handler'];
        self::assertIsObject($this->handler);
    }

    public function testFatalHandlerAllowsADetailPageForAdministratorsOnly(): void
    {
        $method = new ReflectionMethod($this->handler, 'canShowDetails');

        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        self::assertTrue($method->invoke($this->handler));

        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));
        self::assertFalse($method->invoke($this->handler));

        wp_set_current_user(0);
        self::assertFalse($method->invoke($this->handler));
    }

    public function testFatalHandlerDetailedPageRedactsSecretsAndHidesSourceByDefault(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));

        $method = new ReflectionMethod($this->handler, 'display_default_error_template');
        $error = [
            'type' => E_ERROR,
            'message' => 'Fatal password=SUPER_SECRET token=ABC123',
            'file' => __FILE__,
            'line' => __LINE__,
        ];

        ob_start();
        $method->invoke($this->handler, $error, false);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('PHP Fatal Error', $html);
        self::assertStringContainsString('password=••••', $html);
        self::assertStringNotContainsString('SUPER_SECRET', $html);
        self::assertStringNotContainsString('ABC123', $html);
        self::assertStringContainsString('Source display is disabled', $html);
    }

    public function testFatalHandlerGenericPageContainsOnlyReference(): void
    {
        $method = new ReflectionMethod($this->handler, 'displayGeneric');

        ob_start();
        $method->invoke($this->handler);
        $html = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/WAE-[A-F0-9]{16}/', $html);
        self::assertStringContainsString('Something went wrong', $html);
        self::assertStringNotContainsString('Fatal error', $html);
        self::assertStringNotContainsString('Stack trace', $html);

        $references = ErrorHistory::earlyReferences();
        self::assertNotEmpty($references);
        self::assertMatchesRegularExpression('/^WAE-[A-F0-9]{16}$/', $references[0]['reference']);
    }
}
