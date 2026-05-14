<?php

if (!defined('ABSPATH')) {
    exit;
}

trait AutoDocs_Admin_Oauth_Trait
{
    /**
     * Start OAuth in the browser without JavaScript (avoids broken AJAX / missing localized vars).
     */
    public function maybe_redirect_google_oauth_start()
    {
        if (wp_doing_ajax()) {
            return;
        }

        if (empty($_GET['page']) || 'autodocs-publisher' !== $_GET['page']) {
            return;
        }

        if (empty($_GET['autodocs_oauth_go'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'autodocs_oauth_go')) {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'autodocs-publisher',
                        'autodocs_notice' => 'oauth_start_failed',
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }

        $client_id = $this->settings->get('client_id');
        $client_secret = $this->settings->get('client_secret');

        if ($client_id === '' || $client_secret === '') {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'autodocs-publisher',
                        'autodocs_notice' => 'oauth_needs_credentials',
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }

        $state = wp_generate_password(32, false, false);
        set_transient('autodocs_google_oauth_state', $state, 5 * MINUTE_IN_SECONDS);

        $this->redirect_to_google_oauth($state);
        exit;
    }

    /**
     * Redirect browser to Google OAuth. Must not use wp_safe_redirect(): external
     * hosts like accounts.google.com are not in allowed_redirect_hosts, so WordPress
     * falls back to wp-admin (broken OAuth start).
     */
    private function redirect_to_google_oauth($state)
    {
        $url = $this->google_client->auth_url($state);
        if (!is_string($url) || 0 !== strpos($url, 'https://accounts.google.com/')) {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'autodocs-publisher',
                        'autodocs_notice' => 'oauth_start_failed',
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }

        wp_redirect($url);
        exit;
    }

    public function handle_post_google_disconnect()
    {
        check_admin_referer('autodocs_google_disconnect');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'autodocs-publisher'));
        }

        delete_transient('autodocs_google_oauth_state');
        $this->google_client->disconnect();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'autodocs-publisher',
                    'autodocs_notice' => 'disconnected',
                ),
                admin_url('options-general.php')
            )
        );
        exit;
    }

    public function ajax_google_oauth_callback()
    {
        if (empty($_GET['code']) || empty($_GET['state'])) {
            wp_die(esc_html__('Invalid Google OAuth response.', 'autodocs-publisher'));
        }

        $stored = get_transient('autodocs_google_oauth_state');
        delete_transient('autodocs_google_oauth_state');

        $state = sanitize_text_field(wp_unslash($_GET['state']));

        if (!is_string($stored) || $stored === '' || !hash_equals($stored, $state)) {
            wp_die(
                esc_html__(
                    'OAuth state mismatch. Try connecting again (do not use multiple tabs or wait too long before approving).',
                    'autodocs-publisher'
                )
            );
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die(esc_html__('You must be logged in as an administrator to finish connecting Google.', 'autodocs-publisher'));
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $result = $this->google_client->exchange_authorization_code($code);

        if (is_wp_error($result)) {
            $msg = $result->get_error_message();
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => 'autodocs-publisher',
                        'autodocs_notice' => 'oauth_error',
                        'autodocs_oauth_msg' => rawurlencode(wp_strip_all_tags($msg)),
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'autodocs-publisher',
                    'autodocs_notice' => 'oauth_connected',
                ),
                admin_url('options-general.php')
            )
        );
        exit;
    }
}
