<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

final class Settings
{
    public const OPTION = 'wp_aware_errors_theme';

    public static function theme(): string
    {
        if (! function_exists('get_option')) {
            return 'dark';
        }
        $value = get_option(self::OPTION, 'dark');
        return $value === 'light' ? 'light' : 'dark';
    }

    public static function htmlClass(): string
    {
        return self::theme() === 'light' ? ' class="theme-light"' : '';
    }
}
