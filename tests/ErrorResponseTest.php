<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors\Tests;

use Hensh\WpAwareErrors\ErrorResponse;
use Hensh\WpAwareErrors\ExceptionData;
use Hensh\WpAwareErrors\RequestKind;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['_jsonp']);
    }

    public function testGenericJsonContainsNoErrorDetails(): void
    {
        $response = ErrorResponse::generic(RequestKind::JSON, 'WAE-1234567890ABCDEF');
        $payload = json_decode($response['body'], true);

        self::assertSame(500, $response['status']);
        self::assertSame('application/json; charset=UTF-8', $response['headers']['Content-Type']);
        self::assertSame('Something went wrong.', $payload['error']['message'] ?? null);
        self::assertSame('WAE-1234567890ABCDEF', $payload['reference'] ?? null);
        self::assertArrayNotHasKey('type', $payload['error'] ?? []);
    }

    public function testDetailedJsonSanitisesMessageAndRequestData(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('password=TEST_PASSWORD_123'));
        $response = ErrorResponse::build(RequestKind::JSON, true, $error, [
            'likely_source' => ['label' => 'Plugin: shop'],
            'request' => [
                'method' => 'POST',
                'uri' => 'https://example.test/?token=TEST_TOKEN_123',
                'ajax_action' => 'checkout',
                'rest_route' => '/checkout',
            ],
            'hooks' => ['active' => ['init'], 'recent' => []],
        ], 'WAE-1234567890ABCDEF');
        $body = $response['body'];

        self::assertSame(500, $response['status']);
        self::assertStringNotContainsString('TEST_PASSWORD_123', $body);
        self::assertStringNotContainsString('TEST_TOKEN_123', $body);
        self::assertStringContainsString('checkout', $body);
        self::assertStringContainsString('WAE-1234567890ABCDEF', $body);
    }

    public function testXmlAndXmlRpcAreValidStructures(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('boom'));

        $xml = ErrorResponse::build(RequestKind::XML, false, $error, null, 'WAE-1234567890ABCDEF');
        $parsedXml = simplexml_load_string($xml['body']);
        self::assertNotFalse($parsedXml);
        self::assertSame('Something went wrong.', (string) $parsedXml->message);
        self::assertSame('WAE-1234567890ABCDEF', (string) $parsedXml->reference);

        $rpc = ErrorResponse::build(RequestKind::XMLRPC, false, $error, null, 'WAE-1234567890ABCDEF');
        $parsedRpc = simplexml_load_string($rpc['body']);
        self::assertNotFalse($parsedRpc);
        self::assertSame(200, $rpc['status']);
        self::assertSame('text/xml; charset=UTF-8', $rpc['headers']['Content-Type']);
        self::assertSame('methodResponse', $parsedRpc->getName());
        self::assertTrue(isset($parsedRpc->fault));
    }

    public function testJsonpValidatesCallbackAndRejectsInjection(): void
    {
        $error = ExceptionData::fromThrowable(new RuntimeException('boom'));

        $_GET['_jsonp'] = 'safeCallback';
        $valid = ErrorResponse::build(RequestKind::JSONP, false, $error, null, 'WAE-1234567890ABCDEF');
        self::assertStringStartsWith('safeCallback(', $valid['body']);
        self::assertSame('application/javascript; charset=UTF-8', $valid['headers']['Content-Type']);

        $_GET['_jsonp'] = 'alert(1)//';
        $invalid = ErrorResponse::build(RequestKind::JSONP, false, $error, null, 'WAE-1234567890ABCDEF');
        self::assertSame(400, $invalid['status']);
        self::assertSame('application/json; charset=UTF-8', $invalid['headers']['Content-Type']);
        self::assertStringNotContainsString('alert(1)', $invalid['body']);
    }

    public function testGenericCliIsTerminalFriendly(): void
    {
        $response = ErrorResponse::generic(RequestKind::CLI, 'WAE-1234567890ABCDEF');

        self::assertStringContainsString('Something went wrong', $response['body']);
        self::assertStringContainsString('WAE-1234567890ABCDEF', $response['body']);
        self::assertArrayNotHasKey('Content-Type', $response['headers']);
    }

    public function testHtmlResponseContainsNoSensitiveDataWhenGeneric(): void
    {
        $response = ErrorResponse::generic(RequestKind::HTML, 'WAE-1234567890ABCDEF');

        self::assertSame(500, $response['status']);
        self::assertStringNotContainsString('Exception', $response['body']);
        self::assertStringNotContainsString('stack', strtolower($response['body']));
        self::assertStringContainsString('WAE-1234567890ABCDEF', $response['body']);
    }
}
