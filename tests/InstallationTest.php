<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\Installation;
use PHPUnit\Framework\TestCase;

final class InstallationTest extends TestCase
{
    public function testDetectsPluginMuPluginThemeAndStandaloneModes(): void
    {
        $plugin = Installation::detect(WP_PLUGIN_DIR . '/foo/foo.php');
        self::assertSame('plugin', $plugin->mode);
        self::assertSame('Plugin', $plugin->label);
        self::assertTrue($plugin->isPlugin());
        self::assertFalse($plugin->isTheme());

        $mu = Installation::detect(WPMU_PLUGIN_DIR . '/loader.php');
        self::assertSame('mu-plugin', $mu->mode);
        self::assertTrue($mu->isMuPlugin());

        $theme = Installation::detect(WP_CONTENT_DIR . '/themes/twenty/functions.php');
        self::assertSame('theme', $theme->mode);
        self::assertTrue($theme->isTheme());

        $standalone = Installation::detect('/srv/app/entry.php');
        self::assertSame('standalone', $standalone->mode);
    }

    public function testDoesNotMatchPrefixSiblings(): void
    {
        $installation = Installation::detect(WP_CONTENT_DIR . '/plugins-evil/foo.php');

        self::assertSame('standalone', $installation->mode);
    }
}
