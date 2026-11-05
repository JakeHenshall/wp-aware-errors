<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ExceptionData;
use Hensh\WpAwareErrors\Renderer;
use Hensh\WpAwareErrors\Sanitizer;
use Hensh\WpAwareErrors\Settings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RendererTest extends TestCase
{
    protected function setUp(): void
    {
        State::resetPluginIndex();
        unset($GLOBALS['wp_test_options'][Settings::OPTION]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_test_options'][Settings::OPTION]);
    }

    public function testEscapesEveryDynamicValueAndKeepsThePageAccessible(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('<script>alert(1)</script>'));
        $html = Renderer::document($error, $this->context(), [
            ['line' => 10, 'text' => '<img src=x onerror=alert(1)>', 'active' => true],
        ]);

        self::assertStringContainsString('<html lang="en-US"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        self::assertStringContainsString('aria-current="true"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('aria-label="Copy location:', $html);
        self::assertStringContainsString('aria-label="Copy path:', $html);
        self::assertStringContainsString('role="status"', $html);
    }

    public function testEmbedsAiPayloadWithoutBreakingOutOfTheScriptTag(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('</script><script>alert(1)</script>'));
        $html = Renderer::document($error, $this->context(), []);

        self::assertStringNotContainsString('</script><script>alert(1)', $html);
        self::assertStringContainsString('window.__wpAwarePayload=', $html);
        self::assertStringContainsString('\\u003C', $html);
    }

    public function testSanitisedRequestValuesAppearInThePayload(): void
    {
        $context = $this->context();
        $context['request']['query'] = Sanitizer::value('query', ['token' => 'abc', 'page' => '2']);
        $html = Renderer::document(ExceptionData::fromThrowable(new RuntimeException('boom')), $context, []);

        self::assertStringContainsString('••••••••', $html);
        self::assertStringNotContainsString('"token": "abc"', $html);
    }

    public function testLightThemeClassIsRendered(): void
    {
        $GLOBALS['wp_test_options'][Settings::OPTION] = 'light';
        $html = Renderer::document(ExceptionData::fromThrowable(new RuntimeException('boom')), $this->context(), []);

        self::assertStringContainsString('<html lang="en-US" class="theme-light">', $html);
    }

    public function testSourceIsDisabledByDefault(): void
    {
        $html = Renderer::document(
            ExceptionData::fromThrowable(new RuntimeException('boom')),
            $this->context()
        );

        self::assertStringContainsString('Source display is disabled', $html);
        self::assertStringNotContainsString('Source file could not be read', $html);
    }

    public function testInvalidUtf8DoesNotBreakThePage(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException("bad \xC3\x28 bytes"));
        $html = Renderer::document($error, $this->context(), []);

        self::assertStringContainsString('bad', $html);
        self::assertNotSame('', $html);
    }

    public function testEmptyContextStillRenders(): void
    {
        $html = Renderer::document(ExceptionData::fromThrowable(new RuntimeException('boom')), [], []);

        self::assertStringContainsString('No high-confidence solution provider matched this error.', $html);
    }

    /** @return array<string,mixed> */
    private function context(): array
    {
        return [
            'installation' => ['label' => 'Plugin', 'version' => '0.3.0', 'mode' => 'plugin'],
            'environment' => [
                'wordpress' => '6.6',
                'php' => '8.2',
                'environment_type' => 'local',
                'development_mode' => '',
                'multisite' => false,
                'memory_limit' => '128M',
                'wp_memory_limit' => '64M',
                'debug' => true,
                'savequeries' => false,
                'theme' => null,
            ],
            'request' => [
                'method' => 'GET',
                'uri' => Sanitizer::value('uri', '/x?token=abc'),
                'query' => Sanitizer::value('query', ['token' => 'abc']),
                'post' => [],
                'headers' => ['authorization' => Sanitizer::value('authorization', 'Bearer x')],
                'rest_route' => '',
                'rest_params' => [],
                'ajax_action' => '',
                'ajax' => false,
                'rest' => false,
                'cron' => false,
                'cli' => false,
            ],
            'hooks' => [
                'active' => ['init'],
                'recent' => [['name' => 'init', 'args' => []]],
                'ownership' => [],
            ],
            'database' => ['queries' => []],
            'woocommerce' => ['active' => false],
            'compatibility' => ['issues' => []],
            'likely_source' => ['label' => 'Plugin: foo', 'kind' => 'plugin'],
        ];
    }
}
