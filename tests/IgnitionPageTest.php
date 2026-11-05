<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\IgnitionPage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class IgnitionPageTest extends TestCase
{
    public function testContextPanelEscapesAndRedactsValues(): void
    {
        $method = new ReflectionMethod(IgnitionPage::class, 'contextPanel');
        $html = $method->invoke(null, [
            'installation' => ['label' => '<script>alert(1)</script>'],
            'environment' => ['wordpress' => '6.6', 'php' => '8.2', 'environment_type' => 'local', 'theme' => null],
            'request' => ['method' => 'GET', 'uri' => 'https://example.test/?token=abc'],
            'hooks' => ['active' => ['init'], 'recent' => [['name' => 'init', 'args' => ['arg0' => 'password=hunter2']]]],
            'likely_source' => ['label' => 'Plugin: shop'],
            'woocommerce' => ['active' => false],
            'compatibility' => ['issues' => []],
        ]);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('token=abc', $html);
        self::assertStringContainsString('token=••••', $html);
        self::assertStringNotContainsString('hunter2', $html);
        self::assertStringContainsString('password=••••', $html);
        self::assertStringContainsString('<summary', $html);
        self::assertStringContainsString('scope="row"', $html);
    }
}
