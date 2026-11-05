<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\Support\SafeIgnitionReport;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spatie\Ignition\Config\IgnitionConfig;
use Spatie\Ignition\ErrorPage\ErrorPageViewModel;
use Spatie\Ignition\ErrorPage\Renderer;
use Spatie\Ignition\Ignition;

final class SafeIgnitionReportTest extends TestCase
{
    public function testReportRedactsMessagesContextAndDisablesSnippetsAndArguments(): void
    {
        $error = new RuntimeException('DB_PASSWORD=TEST_SECRET_123 sk-test-example-secret');
        [$safeThrowable, $report] = SafeIgnitionReport::create($error, [
            'request' => [
                'method' => 'POST',
                'uri' => 'https://example.test/?access_token=TEST_TOKEN_123',
                'headers' => [
                    'authorization' => 'Bearer TEST_TOKEN_123',
                    'cookie' => 'session=TEST_SECRET_123',
                    'user-agent' => 'Test Agent',
                ],
                'query' => ['password' => 'TEST_PASSWORD_123'],
                'post' => ['token' => 'TEST_TOKEN_123'],
            ],
        ], '/var/www/html');

        $array = $report->toArray();
        $encoded = json_encode($array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        self::assertIsString($encoded);
        foreach (['TEST_SECRET_123', 'TEST_TOKEN_123', 'TEST_PASSWORD_123', 'sk-test-example-secret'] as $secret) {
            self::assertStringNotContainsString($secret, $encoded, $secret);
            self::assertStringNotContainsString($secret, $safeThrowable->getTraceAsString(), $secret);
        }

        self::assertStringNotContainsString('TEST_SECRET_123', (string) ($array['message'] ?? ''));
        self::assertSame('RuntimeException', $array['exception_class'] ?? null);
        self::assertIsArray($array['stacktrace'] ?? null);
        self::assertNotEmpty($array['stacktrace']);
        foreach ($array['stacktrace'] as $frame) {
            self::assertNull($frame['arguments'] ?? null);
            self::assertSame([], $frame['code_snippet'] ?? null);
        }
        self::assertSame([], $array['context']['cookies'] ?? null);
        self::assertSame([], $array['context']['session'] ?? null);
        self::assertStringNotContainsString('TEST_TOKEN_123', (string) ($array['context']['request']['url'] ?? ''));

        $viewModel = new ErrorPageViewModel(
            $safeThrowable,
            new IgnitionConfig(),
            $report,
            [],
            null,
            '',
            ''
        );
        $html = (new Renderer())->renderAsString(
            ['viewModel' => $viewModel],
            Ignition::viewPath('errorPage')
        );

        foreach (['TEST_SECRET_123', 'TEST_TOKEN_123', 'TEST_PASSWORD_123', 'sk-test-example-secret'] as $secret) {
            self::assertStringNotContainsString($secret, $html, $secret);
        }
        self::assertStringContainsString('window.data', $html);
        self::assertStringContainsString('"code_snippet":[]', $html);
    }
}
