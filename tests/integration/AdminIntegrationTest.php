<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests\Integration;

use Hensh\WpAwareErrors\AccessPolicy;
use Hensh\WpAwareErrors\Admin;
use Hensh\WpAwareErrors\ErrorHistory;
use Hensh\WpAwareErrors\ExceptionData;
use Hensh\WpAwareErrors\Settings;
use RuntimeException;
use WP_UnitTestCase;

final class AdminIntegrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        delete_option(Settings::OPTION);
        ErrorHistory::clear();
        unset($_POST['wp_aware_errors_theme'], $_REQUEST['_wpnonce']);
    }

    public function testAdminMenuIsRegistered(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));

        $GLOBALS['submenu'] = [];
        do_action('admin_menu');

        $slugs = array_column($GLOBALS['submenu']['tools.php'] ?? [], 2);
        self::assertContains('wp-aware-errors', $slugs);
        self::assertContains('wp-aware-errors-settings', $slugs);
    }

    public function testSubscribersCannotRenderAdminPages(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));

        ob_start();
        Admin::page();
        Admin::settingsPage();
        $output = (string) ob_get_clean();

        self::assertSame('', $output);
        self::assertFalse(current_user_can('manage_options'));
    }

    public function testThemeSettingIsPersistedAndSanitised(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        $_POST['wp_aware_errors_theme'] = 'light';
        $_REQUEST['_wpnonce'] = wp_create_nonce('wp_aware_errors_save_settings');

        self::assertTrue(Admin::persistSettings());
        self::assertSame('light', Settings::theme());

        $_POST['wp_aware_errors_theme'] = '<script>not-a-theme</script>';
        $_REQUEST['_wpnonce'] = wp_create_nonce('wp_aware_errors_save_settings');
        self::assertTrue(Admin::persistSettings());
        self::assertSame('dark', Settings::theme());

        unset($_POST['wp_aware_errors_theme'], $_REQUEST['_wpnonce']);
    }

    public function testSettingsSaveRequiresAValidNonce(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        $_POST['wp_aware_errors_theme'] = 'light';

        $handler = static function (): callable {
            return static function (): void {
                throw new RuntimeException('wp_die');
            };
        };
        add_filter('wp_die_handler', $handler);

        try {
            $this->expectException(RuntimeException::class);
            Admin::saveSettings();
        } finally {
            remove_filter('wp_die_handler', $handler);
            unset($_POST['wp_aware_errors_theme']);
        }
    }

    public function testHistoryIsClearedWithoutTouchingUnrelatedOptions(): void
    {
        update_option('unrelated_plugin_option', 'keep-me', false);
        ErrorHistory::store(
            ExceptionData::fromThrowable(new RuntimeException('boom')),
            ['likely_source' => ['label' => 'App', 'kind' => 'app'], 'request' => []],
            'WAE-1234567890ABCDEF'
        );

        self::assertNotEmpty(ErrorHistory::all());

        ErrorHistory::clear();

        self::assertSame([], ErrorHistory::all());
        self::assertSame('keep-me', get_option('unrelated_plugin_option'));
        delete_option('unrelated_plugin_option');
    }

    public function testAccessPolicyUsesRealWordPressCapabilities(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        self::assertTrue(AccessPolicy::isAuthorizedAdmin());
        self::assertTrue(AccessPolicy::canViewDetails());

        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));
        self::assertFalse(AccessPolicy::isAuthorizedAdmin());
        self::assertFalse(AccessPolicy::canViewDetails());

        wp_set_current_user(0);
        self::assertFalse(AccessPolicy::isAuthorizedAdmin());
        self::assertFalse(AccessPolicy::canViewDetails());
    }

    public function testAccessPolicyCanBeFilteredWithACapability(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'editor']));

        add_filter('wp_aware_errors_view_capability', static fn (): string => 'edit_posts');
        self::assertTrue(AccessPolicy::isAuthorizedAdmin());

        remove_all_filters('wp_aware_errors_view_capability');
        self::assertFalse(AccessPolicy::isAuthorizedAdmin());
    }

    public function testHistoryClearRequiresAValidNonceAndOnlyClearsPluginHistory(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        update_option('unrelated_plugin_option', 'keep-me', false);

        ErrorHistory::store(
            ExceptionData::fromThrowable(new RuntimeException('boom')),
            ['likely_source' => ['label' => 'App', 'kind' => 'app'], 'request' => []],
            'WAE-1234567890ABCDEF'
        );
        self::assertNotEmpty(ErrorHistory::all());

        $_REQUEST['_wpnonce'] = wp_create_nonce('wp_aware_errors_clear_history');
        self::assertTrue(Admin::clearHistoryData());
        self::assertSame([], ErrorHistory::all());
        self::assertSame('keep-me', get_option('unrelated_plugin_option'));

        unset($_REQUEST['_wpnonce']);
        delete_option('unrelated_plugin_option');
    }

    public function testLegacyHistoryIsMigratedAndCanBeCleared(): void
    {
        update_option('wp_aware_errors_history_v1', [[
            'message' => 'password=TEST_PASSWORD_123',
            'uri' => 'https://example.test/?token=TEST_TOKEN_123',
        ]], false);
        delete_option('wp_aware_errors_history_schema');

        $history = ErrorHistory::all();

        self::assertCount(1, $history);
        self::assertStringNotContainsString('TEST_PASSWORD_123', (string) $history[0]['message']);
        self::assertStringNotContainsString('TEST_TOKEN_123', (string) $history[0]['uri']);
        self::assertTrue(ErrorHistory::hasLegacyNotice());
        self::assertSame(2, (int) get_option('wp_aware_errors_history_schema'));

        ErrorHistory::clearLegacy();

        self::assertFalse(ErrorHistory::hasLegacyNotice());
        self::assertSame([], ErrorHistory::all());
    }
}
