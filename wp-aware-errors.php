<?php
/**
 * Plugin Name: WP Aware Errors
 * Plugin URI:  https://github.com/hensh/wp-aware-errors
 * Description: WordPress-aware exception debugging with component ownership, hook arguments, compatibility intelligence, WooCommerce context, solution providers and local error history.
 * Version:     0.3.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author:      Hensh
 * License:     GPL-2.0-or-later
 * Text Domain: wp-aware-errors
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (class_exists('Hensh\\WpAwareErrors\\Bootstrap', false)) {
    return;
}

define('WP_AWARE_ERRORS_VERSION', '0.3.0');
define('WP_AWARE_ERRORS_FILE', __FILE__);
define('WP_AWARE_ERRORS_DIR', __DIR__);

require_once __DIR__ . '/autoload.php';

\Hensh\WpAwareErrors\Bootstrap::boot(__FILE__);
