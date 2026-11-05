<?php

declare(strict_types=1);

/**
 * Test-only MU-plugin used by tests/http/run.sh in a disposable WordPress
 * installation. It simulates a frontend exception and an administrator session.
 */

add_action('init', static function (): void {
    if (! isset($_GET['wae_http_smoke']) && ! isset($_GET['wae_http_wp_die_fatal'])) {
        return;
    }

    if (isset($_GET['wae_http_admin'])) {
        $user = get_user_by('login', 'admin');
        if ($user) {
            wp_set_current_user($user->ID);
        }
    }

    if (isset($_GET['wae_http_wp_die_fatal'])) {
        wp_die(new WP_Error('internal_server_error', 'HTTP wp_die fatal password=TEST_PASSWORD_123', [
            'error' => [
                'type' => E_ERROR,
                'message' => 'HTTP wp_die fatal password=TEST_PASSWORD_123',
                'file' => __FILE__,
                'line' => __LINE__,
            ],
        ]));
    }

    throw new RuntimeException('HTTP smoke password=TEST_PASSWORD_123 token=TEST_TOKEN_123');
});
