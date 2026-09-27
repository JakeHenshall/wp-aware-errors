<?php
/**
 * Plugin Name: WP Aware Errors
 * Plugin URI:  https://github.com/hensh/wp-aware-errors
 * Description: WordPress context on Spatie Ignition: component ownership, hooks, compatibility, WooCommerce, solutions and local error history.
 * Version:     0.3.0
 * Requires at least: 6.2
 * Requires PHP: 8.2
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

$vendor = __DIR__ . '/vendor/autoload.php';
if (is_readable($vendor)) {
    require_once $vendor;
}

require_once __DIR__ . '/autoload.php';

\Hensh\WpAwareErrors\Bootstrap::boot(__FILE__);
