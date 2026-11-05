<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

/**
 * Opaque correlation reference for a single handled error.
 *
 * The reference is intentionally random and is never used as a lookup key on
 * a public endpoint. It only correlates a generic page with a local history
 * record for administrators.
 */
final class ErrorReference
{
    public static function generate(): string
    {
        try {
            $hex = strtoupper(bin2hex(random_bytes(8)));
        } catch (\Throwable) {
            try {
                $hex = strtoupper(str_pad(dechex(random_int(0, 0xFFFFFFFF)), 8, '0', STR_PAD_LEFT));
            } catch (\Throwable) {
                $hex = strtoupper(substr(hash('sha256', uniqid('', true) . microtime(true)), 0, 16));
            }
        }

        return 'WAE-' . $hex;
    }

    public static function isValid(string $reference): bool
    {
        return preg_match('/^WAE-[A-F0-9]{8,32}$/', $reference) === 1;
    }
}
