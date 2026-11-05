<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\HookRecorder;
use PHPUnit\Framework\TestCase;

final class HookRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        State::resetHookRecorder();
        $GLOBALS['wp_current_filter'] = [];
        $GLOBALS['wp_test_current_filter'] = '';
    }

    protected function tearDown(): void
    {
        State::resetHookRecorder();
        $GLOBALS['wp_current_filter'] = [];
        $GLOBALS['wp_test_current_filter'] = '';
    }

    public function testKeepsOnlyTheMostRecentHooks(): void
    {
        $GLOBALS['wp_test_current_filter'] = 'init';

        for ($i = 0; $i < 60; $i++) {
            self::assertSame('value-' . $i, HookRecorder::record('value-' . $i));
        }

        self::assertCount(50, HookRecorder::recentDetailed(50));
        self::assertSame(['init', 'init', 'init'], HookRecorder::recentNames(3));
        self::assertSame('value-59', HookRecorder::recentDetailed(1)[0]['args']['arg0']);
    }

    public function testSkipsArgumentsForFatHooks(): void
    {
        $GLOBALS['wp_test_current_filter'] = 'alloptions';
        HookRecorder::record('huge-options-tree');

        $recent = HookRecorder::recentDetailed(1);
        self::assertSame('alloptions', $recent[0]['name']);
        self::assertSame([], $recent[0]['args']);
    }

    public function testRedactsSecretsInStringArguments(): void
    {
        $GLOBALS['wp_test_current_filter'] = 'init';
        HookRecorder::record('password=hunter2');

        $recent = HookRecorder::recentDetailed(1);
        self::assertStringNotContainsString('hunter2', (string) $recent[0]['args']['arg0']);
        self::assertStringContainsString('password=••••', (string) $recent[0]['args']['arg0']);
    }

    public function testFallsBackToTheAggregateHookArgument(): void
    {
        $GLOBALS['wp_test_current_filter'] = 'all';
        HookRecorder::record('the_content', 'value');

        $recent = HookRecorder::recentDetailed(1);
        self::assertSame('the_content', $recent[0]['name']);
    }

    public function testActiveStackUsesWordPressFilterStack(): void
    {
        $GLOBALS['wp_current_filter'] = ['init', 'save_post', 'save_post'];

        self::assertSame(['init', 'save_post', 'save_post'], HookRecorder::activeStack());
    }
}
