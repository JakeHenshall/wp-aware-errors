<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\PluginThemeIndex;
use PHPUnit\Framework\TestCase;

final class PluginThemeIndexTest extends TestCase
{
    protected function setUp(): void
    {
        State::resetPluginIndex();
        $GLOBALS['wp_test_plugins'] = [];
        $GLOBALS['wp_test_file_data'] = [];
        $GLOBALS['wp_test_options'] = [];
        $GLOBALS['wp_test_theme'] = null;

        $dir = WP_PLUGIN_DIR . '/foo';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/foo.php', '<?php');
    }

    protected function tearDown(): void
    {
        State::resetPluginIndex();
        $GLOBALS['wp_test_plugins'] = [];
        $GLOBALS['wp_test_file_data'] = [];
        $GLOBALS['wp_test_theme'] = null;

        @unlink(WP_PLUGIN_DIR . '/foo/foo.php');
        @rmdir(WP_PLUGIN_DIR . '/foo');
    }

    public function testIndexesInstalledPluginsAndTheirMetadata(): void
    {
        $GLOBALS['wp_test_plugins'] = [
            'foo/foo.php' => [
                'Name' => 'Foo',
                'Version' => '1.2.3',
                'RequiresWP' => '6.0',
                'RequiresPHP' => '8.1',
                'RequiresPlugins' => 'bar, baz',
            ],
            'bar/bar.php' => [
                'Name' => 'Bar',
                'Version' => '2.0',
            ],
        ];
        $GLOBALS['wp_test_options']['active_plugins'] = ['foo/foo.php'];
        $GLOBALS['wp_test_file_data'][WP_PLUGIN_DIR . '/foo/foo.php'] = [
            'WCRequires' => '8.0',
            'WCTested' => '9.0',
        ];

        PluginThemeIndex::prime();

        $foo = PluginThemeIndex::plugin('foo');
        self::assertNotNull($foo);
        self::assertSame('Foo', $foo['name']);
        self::assertSame('1.2.3', $foo['version']);
        self::assertTrue($foo['active']);
        self::assertSame(['bar', 'baz'], $foo['requires_plugins']);
        self::assertSame('8.0', $foo['wc_requires']);
        self::assertSame('9.0', $foo['wc_tested']);

        $bar = PluginThemeIndex::plugin('bar');
        self::assertNotNull($bar);
        self::assertFalse($bar['active']);
        self::assertCount(2, PluginThemeIndex::plugins());
    }

    public function testIndexesChildAndParentThemes(): void
    {
        $parent = new \WP_Test_Theme(['Name' => 'Parent', 'Version' => '2.0', 'stylesheet' => 'parent']);
        $GLOBALS['wp_test_theme'] = new \WP_Test_Theme(['Name' => 'Child', 'Version' => '1.0', 'stylesheet' => 'child'], $parent);

        PluginThemeIndex::primeThemes();

        self::assertSame('Child', PluginThemeIndex::theme('child')['name'] ?? null);
        self::assertSame('2.0', PluginThemeIndex::theme('parent')['version'] ?? null);
    }
}
