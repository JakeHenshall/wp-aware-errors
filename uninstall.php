<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (function_exists('delete_option')) {
    delete_option('wp_aware_errors_theme');
    delete_option('wp_aware_errors_history_v1');
    delete_option('wp_aware_errors_history_schema');
    delete_option('wp_aware_errors_history_legacy_notice');
    delete_option('wp_aware_errors_history_quarantine');
    delete_option('wp_aware_errors_early_fatal_references');
}
