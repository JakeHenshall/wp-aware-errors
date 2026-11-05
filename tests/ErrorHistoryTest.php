<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ErrorHistory;
use Hensh\WpAwareErrors\ExceptionData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHistoryTest extends TestCase
{
    private const OPTION = 'wp_aware_errors_history_v1';

    protected function setUp(): void
    {
        $GLOBALS['wp_test_options'] = [];
        $GLOBALS['wp_test_option_autoload'] = [];
    }

    public function testStoresSanitisedCompactEntriesWithoutAutoloading(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('Login failed password=hunter2'));
        $context = [
            'likely_source' => ['label' => 'Plugin: shop', 'kind' => 'plugin'],
            'request' => [
                'method' => 'GET',
                'uri' => 'https://example.test/?token=abc',
                'ajax_action' => '',
                'rest_route' => '',
            ],
        ];

        ErrorHistory::store($error, $context);

        $all = ErrorHistory::all();
        self::assertCount(1, $all);
        self::assertStringNotContainsString('hunter2', (string) $all[0]['message']);
        self::assertStringContainsString('password=••••', (string) $all[0]['message']);
        self::assertStringNotContainsString('token=abc', (string) $all[0]['uri']);
        self::assertStringContainsString('token=••••', (string) $all[0]['uri']);
        self::assertArrayNotHasKey('id', $all[0]);
        self::assertMatchesRegularExpression('/^WAE-[A-F0-9]{16}$/', (string) $all[0]['reference']);
        self::assertArrayHasKey(self::OPTION, $GLOBALS['wp_test_option_autoload']);
        self::assertFalse($GLOBALS['wp_test_option_autoload'][self::OPTION]);
    }

    public function testReturnsNewestFirstAndCapsHistory(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('boom'));
        $context = ['likely_source' => ['label' => 'App', 'kind' => 'app'], 'request' => []];

        for ($i = 0; $i < 35; $i++) {
            ErrorHistory::store($error, $context);
        }

        $all = ErrorHistory::all();
        self::assertCount(30, $all);
        self::assertSame('boom', $all[0]['message']);
    }

    public function testHandlesCorruptOptionsAndClears(): void
    {
        $GLOBALS['wp_test_options'][self::OPTION] = 'not-an-array';

        self::assertSame([], ErrorHistory::all());

        $GLOBALS['wp_test_options'][self::OPTION] = [
            ['message' => 'ok'],
            'not-an-array',
        ];
        self::assertCount(1, ErrorHistory::all());

        ErrorHistory::clear();
        self::assertArrayNotHasKey(self::OPTION, $GLOBALS['wp_test_options']);
        self::assertArrayNotHasKey('wp_aware_errors_history_quarantine', $GLOBALS['wp_test_options']);
        self::assertArrayNotHasKey('wp_aware_errors_history_legacy_notice', $GLOBALS['wp_test_options']);
    }

    public function testEarlyFatalReferencesAreOpaqueAndBounded(): void
    {
        for ($i = 0; $i < 12; $i++) {
            ErrorHistory::addEarlyReference('WAE-' . str_pad((string) $i, 16, '0', STR_PAD_LEFT));
        }

        $references = ErrorHistory::earlyReferences();
        self::assertCount(10, $references);
        self::assertMatchesRegularExpression('/^WAE-[0-9A-F]{16}$/', $references[0]['reference']);
        self::assertArrayNotHasKey('message', $references[0]);

        ErrorHistory::clear();
        self::assertFalse(ErrorHistory::hasEarlyReferences());
    }

    public function testClearLegacyPreservesNewReferenceRecords(): void
    {
        $GLOBALS['wp_test_options'][self::OPTION] = [
            ['reference' => 'WAE-1234567890ABCDEF', 'message' => 'new record', 'uri' => ''],
            ['message' => 'legacy record', 'uri' => 'https://example.test/?token=TEST_TOKEN_123'],
        ];
        $GLOBALS['wp_test_options']['wp_aware_errors_history_schema'] = 2;

        ErrorHistory::clearLegacy();

        $all = ErrorHistory::all();
        self::assertCount(1, $all);
        self::assertSame('WAE-1234567890ABCDEF', $all[0]['reference']);
        self::assertSame('new record', $all[0]['message']);
    }

    public function testMigratesLegacyRecordsOnceAndQuarantinesUnreadableEntries(): void
    {
        $GLOBALS['wp_test_options'][self::OPTION] = [
            [
                'time' => '2024-01-01T00:00:00+00:00',
                'type' => 'RuntimeException',
                'message' => 'password=TEST_PASSWORD_123',
                'uri' => 'https://example.test/?access_token=TEST_TOKEN_123',
                'unknown_secret' => 'TEST_SECRET_123',
            ],
            'not-a-record',
        ];

        $all = ErrorHistory::all();

        self::assertCount(1, $all);
        self::assertStringNotContainsString('TEST_PASSWORD_123', (string) $all[0]['message']);
        self::assertStringNotContainsString('TEST_TOKEN_123', (string) $all[0]['uri']);
        self::assertArrayNotHasKey('unknown_secret', $all[0]);
        self::assertTrue(ErrorHistory::hasLegacyNotice());
        self::assertTrue(ErrorHistory::hasQuarantinedRecords());
        self::assertSame(1, ErrorHistory::quarantinedCount());
        self::assertSame(2, (int) ($GLOBALS['wp_test_options']['wp_aware_errors_history_schema'] ?? 0));
        $quarantine = $GLOBALS['wp_test_options']['wp_aware_errors_history_quarantine'] ?? [];
        self::assertIsArray($quarantine);
        self::assertArrayNotHasKey(0, $quarantine);
        self::assertStringNotContainsString('not-a-record', json_encode($quarantine) ?: '');

        // Re-running the migration must not duplicate records.
        ErrorHistory::migrateLegacy();
        self::assertCount(1, ErrorHistory::all());

        ErrorHistory::clearLegacy();
        self::assertFalse(ErrorHistory::hasLegacyNotice());
        self::assertFalse(ErrorHistory::hasQuarantinedRecords());
    }
}
