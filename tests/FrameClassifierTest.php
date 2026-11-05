<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\FrameClassifier;
use PHPUnit\Framework\TestCase;

final class FrameClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        State::resetPluginIndex();
        $GLOBALS['wp_test_plugins'] = [];
        $GLOBALS['wp_test_file_data'] = [];
    }

    public function testClassifiesPluginMuPluginThemeVendorCoreAndAppFiles(): void
    {
        $plugin = FrameClassifier::classify(WP_PLUGIN_DIR . '/foo/includes/loader.php');
        self::assertSame('plugin', $plugin['kind']);
        self::assertSame('foo', $plugin['slug']);
        self::assertFalse($plugin['internal']);

        $mu = FrameClassifier::classify(WPMU_PLUGIN_DIR . '/loader.php');
        self::assertSame('mu-plugin', $mu['kind']);
        self::assertSame('MU Plugin: loader', $mu['label']);

        $theme = FrameClassifier::classify(WP_CONTENT_DIR . '/themes/twenty/template.php');
        self::assertSame('theme', $theme['kind']);
        self::assertSame('twenty', $theme['slug']);

        $vendor = FrameClassifier::classify(ABSPATH . 'vendor/pkg/file.php');
        self::assertSame('vendor', $vendor['kind']);
        self::assertTrue($vendor['internal']);

        $core = FrameClassifier::classify(ABSPATH . 'wp-includes/plugin.php');
        self::assertSame('core', $core['kind']);
        self::assertTrue($core['internal']);

        $app = FrameClassifier::classify('/srv/app/file.php');
        self::assertSame('app', $app['kind']);
        self::assertFalse($app['internal']);
    }

    public function testEmptyPathIsRuntime(): void
    {
        $runtime = FrameClassifier::classify('');

        self::assertSame('runtime', $runtime['kind']);
        self::assertTrue($runtime['internal']);
    }

    public function testRelativePathStripsAbspath(): void
    {
        self::assertSame(
            'wp-content/plugins/foo/includes/loader.php',
            FrameClassifier::relative(WP_PLUGIN_DIR . '/foo/includes/loader.php')
        );
        self::assertSame('/srv/app/file.php', FrameClassifier::relative('/srv/app/file.php'));
    }
}
