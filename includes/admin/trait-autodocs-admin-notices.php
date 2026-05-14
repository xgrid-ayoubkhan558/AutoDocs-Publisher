<?php

if (!defined('ABSPATH')) {
    exit;
}

trait AutoDocs_Admin_Notices_Trait
{
    private function notice_reconnect_scopes()
    {
        if (! $this->google_client->is_connected()) {
            return;
        }
        if (! $this->google_client->tokens_need_reconnect_for_scopes()) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Google permissions for this plugin were updated. Disconnect Google under the Settings tab, then connect again so imports and Drive moves work (full Drive + Docs + email scope).', 'autodocs-publisher');
        echo '</p></div>';
    }

    private function notice()
    {
        if (empty($_GET['autodocs_notice'])) {
            return;
        }

        if ('oauth_connected' === $_GET['autodocs_notice']) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Google account connected.', 'autodocs-publisher') . '</p></div>';
        }

        if ('oauth_error' === $_GET['autodocs_notice']) {
            $detail = '';
            if (!empty($_GET['autodocs_oauth_msg'])) {
                $decoded = rawurldecode((string) wp_unslash($_GET['autodocs_oauth_msg']));
                $snippet = substr(wp_strip_all_tags($decoded), 0, 500);
                $detail = $snippet !== '' ? ' ' . esc_html($snippet) : '';
            }
            echo '<div class="notice notice-error"><p>' . esc_html__('Google OAuth connection failed.', 'autodocs-publisher') . $detail . '</p></div>';
        }

        if ('oauth_needs_credentials' === $_GET['autodocs_notice']) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Save your Google Client ID and Client Secret below, then click Connect again.', 'autodocs-publisher') . '</p></div>';
        }

        if ('oauth_start_failed' === $_GET['autodocs_notice']) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Could not start Google sign-in. Refresh this page and try again.', 'autodocs-publisher') . '</p></div>';
        }

        if ('disconnected' === $_GET['autodocs_notice']) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Google has been disconnected.', 'autodocs-publisher') . '</p></div>';
        }
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function plugin_action_links($links)
    {
        $settings_url = admin_url('options-general.php?page=autodocs-publisher');
        $label = $this->google_client->is_connected()
            ? __('Google API: Connected', 'autodocs-publisher')
            : __('Google API: Not connected', 'autodocs-publisher');

        $links[] = '<a href="' . esc_url($settings_url) . '">' . esc_html($label) . '</a>';

        return $links;
    }
}
