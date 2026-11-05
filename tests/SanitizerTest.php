<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\Sanitizer;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SanitizerTest extends TestCase
{
    public function testRedactsSensitiveKeysAndKeepsSafeOnes(): void
    {
        $value = [
            'user' => 'admin',
            'password' => 'hunter2',
            'nested' => [
                'accessToken' => 'abc',
                'apiKey' => 'key',
                'clientSecret' => 'secret',
                'privateKey' => 'private',
                'x-wp-nonce' => 'nonce',
                'compass' => 'north',
                'author' => 'Ada',
            ],
        ];

        $out = Sanitizer::value('payload', $value);

        self::assertSame('admin', $out['user']);
        self::assertSame('••••••••', $out['password']);
        self::assertSame('••••••••', $out['nested']['accessToken']);
        self::assertSame('••••••••', $out['nested']['apiKey']);
        self::assertSame('••••••••', $out['nested']['clientSecret']);
        self::assertSame('••••••••', $out['nested']['privateKey']);
        self::assertSame('••••••••', $out['nested']['x-wp-nonce']);
        self::assertSame('north', $out['nested']['compass']);
        self::assertSame('Ada', $out['nested']['author']);
    }

    public function testRedactsPluralSecretKeys(): void
    {
        self::assertSame('••••••••', Sanitizer::value('passwords', 'x'));
        self::assertSame('••••••••', Sanitizer::value('access_tokens', 'x'));
        self::assertSame('••••••••', Sanitizer::value('nonces', 'x'));
    }

    public function testRedactsUrlsWithoutLosingSafeParameters(): void
    {
        $url = 'https://user:pass@example.test/path?token=abc&foo=bar&_wpnonce=xyz#frag';

        self::assertSame(
            'https://••••@example.test/path?token=••••&foo=bar&_wpnonce=••••#frag',
            Sanitizer::url($url)
        );
        self::assertSame(
            'https://••••@example.test/path?token=••••&foo=bar&_wpnonce=••••#frag',
            Sanitizer::value('uri', $url)
        );
    }

    public function testRedactsSecretsInFreeFormTextAndSql(): void
    {
        $text = 'Login failed password=hunter2 token: abc Bearer sk_live_123';
        $redacted = Sanitizer::text($text);

        self::assertStringNotContainsString('hunter2', $redacted);
        self::assertStringNotContainsString('sk_live_123', $redacted);
        self::assertStringContainsString('password=••••', $redacted);
        self::assertStringContainsString('token: ••••', $redacted);
        self::assertStringContainsString('Bearer ••••', $redacted);

        $sql = Sanitizer::sql("SELECT * FROM wp_users WHERE user_pass = 'secret'");
        self::assertStringNotContainsString('secret', $sql);
        self::assertStringContainsString('user_pass', $sql);
    }

    public function testCapsStringsAndStructuredDepth(): void
    {
        $long = str_repeat('a', 700);
        $out = Sanitizer::value('message', $long);
        self::assertIsString($out);
        self::assertLessThanOrEqual(503, strlen($out));
        self::assertStringEndsWith('…', $out);

        $nested = Sanitizer::argument(['one' => ['two' => ['three' => 'value']]]);
        self::assertSame('[depth limited]', $nested['one']['two']);
    }

    public function testReducesLargeArraysToASummary(): void
    {
        $value = [];
        for ($i = 0; $i < 40; $i++) {
            $value['key' . $i] = 'value' . $i;
        }

        $out = Sanitizer::value('query', $value);
        self::assertSame('array', $out['__type']);
        self::assertSame(40, $out['count']);
        self::assertCount(30, $out['keys']);
        self::assertSame('key0', $out['keys'][0]);
    }

    public function testObjectsExposeNoPropertiesExceptKnownWordPressTypes(): void
    {
        self::assertSame(['__class' => stdClass::class], Sanitizer::value('object', new stdClass()));
    }

    public function testRestParamsAreDepthLimited(): void
    {
        $out = Sanitizer::restParams([
            'id' => 12,
            'filters' => ['status' => ['draft', 'publish']],
    ]);

        self::assertSame(12, $out['id']);
        self::assertSame('[depth limited]', $out['filters']['status']);
    }
}
