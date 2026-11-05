<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

/**
 * Builds protocol-appropriate error responses. The caller decides whether
 * detailed data is allowed via AccessPolicy.
 */
final class ErrorResponse
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /** @return array{status:int,headers:array<string,string>,body:string} */
    public static function build(string $kind, bool $detailed, ExceptionData $error, ?array $context, string $reference): array
    {
        if ($detailed && ! AccessPolicy::canViewDetails()) {
            $detailed = false;
        }
        if (! ErrorReference::isValid($reference)) {
            $reference = ErrorReference::generate();
        }

        return match ($kind) {
            RequestKind::JSON => self::jsonResponse($detailed, $error, $context, $reference),
            RequestKind::JSONP => self::jsonpResponse($detailed, $error, $context, $reference),
            RequestKind::XML => self::xmlResponse($detailed, $error, $reference),
            RequestKind::XMLRPC => self::xmlRpcResponse($detailed, $error, $reference),
            RequestKind::CLI => self::cliResponse($detailed, $error, $context, $reference),
            default => self::htmlResponse($reference),
        };
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    public static function generic(string $kind, string $reference): array
    {
        if (! ErrorReference::isValid($reference)) {
            $reference = ErrorReference::generate();
        }

        return match ($kind) {
            RequestKind::JSON => self::jsonGeneric($reference),
            RequestKind::JSONP => self::jsonpGeneric($reference),
            RequestKind::XML => self::xmlGeneric($reference),
            RequestKind::XMLRPC => self::xmlRpcGeneric($reference),
            RequestKind::CLI => [
                'status' => 500,
                'headers' => [],
                'body' => "Something went wrong\nReference: {$reference}\n",
            ],
            default => [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
                'body' => GenericErrorPage::html($reference),
            ],
        };
    }

    /** @param array{status:int,headers:array<string,string>,body:string} $response */
    public static function send(array $response, string $kind): void
    {
        if ($kind === RequestKind::CLI) {
            if (defined('STDERR')) {
                fwrite(STDERR, $response['body']);
            } else {
                echo $response['body'];
            }
            return;
        }

        if (! headers_sent()) {
            if (function_exists('status_header')) {
                status_header($response['status']);
            } else {
                http_response_code($response['status']);
            }
            foreach ($response['headers'] as $name => $value) {
                header($name . ': ' . $value);
            }
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Content-Type-Options: nosniff', true);
        }

        echo $response['body'];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function htmlResponse(string $reference): array
    {
        // Detailed HTML is owned by ErrorPresenter (Ignition/Renderer after the
        // access policy). This builder is a safe generic fallback only.
        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
            'body' => GenericErrorPage::html($reference),
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function jsonResponse(bool $detailed, ExceptionData $error, ?array $context, string $reference): array
    {
        $payload = $detailed ? self::detailedPayload($error, $context, $reference) : self::genericPayload($reference);
        $json = json_encode($payload, self::JSON_FLAGS);

        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
            'body' => $json === false ? '{"error":{"message":"Something went wrong."}}' : $json,
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function jsonpResponse(bool $detailed, ExceptionData $error, ?array $context, string $reference): array
    {
        $callback = self::jsonpCallback();
        if ($callback === null) {
            // Invalid or disabled callbacks are never reflected. Return plain JSON.
            $payload = $detailed ? self::detailedPayload($error, $context, $reference) : self::genericPayload($reference);
            $json = json_encode($payload, self::JSON_FLAGS);
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
                'body' => $json === false ? '{"error":{"message":"Something went wrong."}}' : $json,
            ];
        }

        $payload = $detailed ? self::detailedPayload($error, $context, $reference) : self::genericPayload($reference);
        $json = json_encode($payload, self::JSON_FLAGS);
        if ($json === false) {
            $json = '{"error":{"message":"Something went wrong."}}';
        }

        return [
            'status' => 500,
            'headers' => [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => $callback . '(' . $json . ');',
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function xmlResponse(bool $detailed, ExceptionData $error, string $reference): array
    {
        $message = $detailed
            ? (string) Sanitizer::value('message', $error->message)
            : 'Something went wrong.';
        $body = '<?xml version="1.0" encoding="UTF-8"?><error>'
            . '<message>' . self::xml($message) . '</message>'
            . '<reference>' . self::xml($reference) . '</reference>';
        if ($detailed) {
            $body .= '<type>' . self::xml($error->type) . '</type>'
                . '<file>' . self::xml(FrameClassifier::relative($error->file)) . '</file>'
                . '<line>' . $error->line . '</line>';
        }
        $body .= '</error>';

        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'application/xml; charset=UTF-8'],
            'body' => $body,
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function xmlRpcResponse(bool $detailed, ExceptionData $error, string $reference): array
    {
        $message = $detailed
            ? (string) Sanitizer::value('message', $error->message)
            : 'Something went wrong.';
        $message .= ' Reference: ' . $reference;

        $body = '<?xml version="1.0" encoding="UTF-8"?><methodResponse><fault><value><struct>'
            . '<member><name>faultCode</name><value><int>-32603</int></value></member>'
            . '<member><name>faultString</name><value><string>' . self::xml($message) . '</string></value></member>'
            . '</struct></value></fault></methodResponse>';

        // XML-RPC transports faults as a valid methodResponse, typically with HTTP 200.
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/xml; charset=UTF-8'],
            'body' => $body,
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function cliResponse(bool $detailed, ExceptionData $error, ?array $context, string $reference): array
    {
        if ($detailed) {
            return [
                'status' => 500,
                'headers' => [],
                'body' => Renderer::cliText($error, $context ?? [], $reference),
            ];
        }

        return [
            'status' => 500,
            'headers' => [],
            'body' => "Something went wrong\nReference: {$reference}\n",
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function jsonGeneric(string $reference): array
    {
        $json = json_encode(self::genericPayload($reference), self::JSON_FLAGS);
        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
            'body' => $json === false ? '{"error":{"message":"Something went wrong."}}' : $json,
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function jsonpGeneric(string $reference): array
    {
        $callback = self::jsonpCallback();
        $json = json_encode(self::genericPayload($reference), self::JSON_FLAGS);
        if ($json === false) {
            $json = '{"error":{"message":"Something went wrong."}}';
        }

        if ($callback === null) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
                'body' => $json,
            ];
        }

        return [
            'status' => 500,
            'headers' => [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => $callback . '(' . $json . ');',
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function xmlGeneric(string $reference): array
    {
        return [
            'status' => 500,
            'headers' => ['Content-Type' => 'application/xml; charset=UTF-8'],
            'body' => '<?xml version="1.0" encoding="UTF-8"?><error><message>Something went wrong.</message><reference>'
                . self::xml($reference) . '</reference></error>',
        ];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function xmlRpcGeneric(string $reference): array
    {
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/xml; charset=UTF-8'],
            'body' => '<?xml version="1.0" encoding="UTF-8"?><methodResponse><fault><value><struct>'
                . '<member><name>faultCode</name><value><int>-32603</int></value></member>'
                . '<member><name>faultString</name><value><string>Something went wrong. Reference: '
                . self::xml($reference) . '</string></value></member>'
                . '</struct></value></fault></methodResponse>',
        ];
    }

    /** @return array{error:array<string,mixed>,reference:string} */
    private static function genericPayload(string $reference): array
    {
        return [
            'error' => ['message' => 'Something went wrong.'],
            'reference' => $reference,
        ];
    }

    /** @return array<string,mixed> */
    private static function detailedPayload(ExceptionData $error, ?array $context, string $reference): array
    {
        $context = $context ?? [];
        $source = (array) ($context['likely_source'] ?? []);
        $request = (array) ($context['request'] ?? []);
        $hooks = (array) ($context['hooks'] ?? []);

        return [
            'error' => [
                'type' => $error->type,
                'message' => (string) Sanitizer::value('message', $error->message),
                'file' => FrameClassifier::relative($error->file),
                'line' => $error->line,
            ],
            'source' => (string) Sanitizer::text((string) ($source['label'] ?? 'Unknown')),
            'request' => [
                'method' => (string) ($request['method'] ?? ''),
                'uri' => (string) Sanitizer::value('uri', $request['uri'] ?? ''),
                'ajax_action' => (string) Sanitizer::value('action', $request['ajax_action'] ?? ''),
                'rest_route' => (string) Sanitizer::value('route', $request['rest_route'] ?? ''),
            ],
            'hooks' => [
                'active' => array_slice(array_map('strval', (array) ($hooks['active'] ?? [])), -10),
                'recent' => Sanitizer::value('recent', array_slice((array) ($hooks['recent'] ?? []), -8)),
            ],
            'reference' => $reference,
        ];
    }

    private static function jsonpCallback(): ?string
    {
        $raw = $_GET['_jsonp'] ?? null;
        if (! is_string($raw) || $raw === '' || strlen($raw) > 128) {
            return null;
        }

        if (function_exists('wp_check_jsonp_callback')) {
            return wp_check_jsonp_callback($raw) ? $raw : null;
        }

        return preg_match('/^[A-Za-z_$][0-9A-Za-z_$]*(?:\.[A-Za-z_$][0-9A-Za-z_$]*)*$/', $raw) === 1
            ? $raw
            : null;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
