<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

/**
 * Classifies the current request once, using WordPress-native detection where
 * available. Unknown request types fall back to a safe HTML response.
 */
final class RequestKind
{
    public const HTML = 'html';
    public const JSON = 'json';
    public const JSONP = 'jsonp';
    public const XMLRPC = 'xmlrpc';
    public const XML = 'xml';
    public const CLI = 'cli';

    public static function detect(): string
    {
        if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI)) {
            return self::CLI;
        }
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return self::XMLRPC;
        }
        if (function_exists('wp_is_jsonp_request') && wp_is_jsonp_request()) {
            return self::JSONP;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return self::JSON;
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return self::JSON;
        }
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return self::JSON;
        }
        if (function_exists('wp_is_xml_request') && wp_is_xml_request()) {
            return self::XML;
        }
        if (function_exists('is_feed') && isset($GLOBALS['wp_query']) && is_object($GLOBALS['wp_query']) && is_feed()) {
            return self::XML;
        }

        return self::HTML;
    }

    public static function isNonHtml(string $kind): bool
    {
        return $kind !== self::HTML;
    }
}
