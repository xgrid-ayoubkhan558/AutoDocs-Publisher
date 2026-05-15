<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoDocs_Settings
{
    const OPTION_NAME = 'autodocs_publisher_settings';
    const TOKEN_OPTION = 'autodocs_publisher_google_tokens';

    public static function add_defaults()
    {
        $defaults = array(
            'client_id' => '',
            'client_secret' => '',
            'working_folder_id' => '',
            'working_folder_name' => '',
            'folder_synced' => '',
            'folder_new' => '',
            'folder_modified' => '',
            'acf_body_field' => '',
            'folder_missing' => '',
        );

        if (false === get_option(self::OPTION_NAME, false)) {
            add_option(self::OPTION_NAME, $defaults, '', false);
        }
    }

    public function register()
    {
        register_setting(
            'autodocs_publisher',
            self::OPTION_NAME,
            array('sanitize_callback' => array($this, 'sanitize'))
        );
    }

    public function sanitize($input)
    {
        $input = is_array($input) ? $input : array();

        $existing = $this->all();
        $old_id = isset($existing['client_id']) ? (string) $existing['client_id'] : '';
        $old_secret = isset($existing['client_secret']) ? (string) $existing['client_secret'] : '';
        $new_id = isset($input['client_id']) ? sanitize_text_field($input['client_id']) : '';
        $new_secret = isset($input['client_secret']) ? sanitize_text_field($input['client_secret']) : '';

        if ($old_id !== $new_id || $old_secret !== $new_secret) {
            $this->clear_tokens();
        }

        return array(
            'client_id' => isset($input['client_id']) ? sanitize_text_field($input['client_id']) : '',
            'client_secret' => isset($input['client_secret']) ? sanitize_text_field($input['client_secret']) : '',
            'working_folder_id' => isset($input['working_folder_id']) ? sanitize_text_field($input['working_folder_id']) : '',
            'working_folder_name' => isset($input['working_folder_name']) ? sanitize_text_field($input['working_folder_name']) : '',
            'folder_synced' => isset($input['folder_synced']) ? sanitize_text_field($input['folder_synced']) : '',
            'folder_new' => isset($input['folder_new']) ? sanitize_text_field($input['folder_new']) : '',
            'folder_modified' => '',
            'acf_body_field' => (array_key_exists('acf_body_field', $input) || array_key_exists('acf_body_field_custom', $input))
                ? $this->sanitize_acf_body_field_input($input)
                : (isset($existing['acf_body_field']) ? (string) $existing['acf_body_field'] : ''),
            'folder_missing' => isset($input['folder_missing']) ? sanitize_text_field($input['folder_missing']) : '',
        );
    }

    public function all()
    {
        return wp_parse_args(get_option(self::OPTION_NAME, array()), array(
            'client_id' => '',
            'client_secret' => '',
            'working_folder_id' => '',
            'working_folder_name' => '',
            'folder_synced' => '',
            'folder_new' => '',
            'folder_modified' => '',
            'acf_body_field' => '',
            'folder_missing' => '',
        ));
    }

    public function get($key, $default = null)
    {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Map HTML body to post_content, or to an ACF field via update_field().
     *
     * @param array<string, mixed> $input
     * @return string
     */
    private function sanitize_acf_body_field_input(array $input)
    {
        $sel = isset($input['acf_body_field']) ? sanitize_text_field((string) $input['acf_body_field']) : '';
        $custom = isset($input['acf_body_field_custom']) ? sanitize_text_field((string) $input['acf_body_field_custom']) : '';

        if ($sel === AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE) {
            return $custom;
        }

        return $sel;
    }

    public function get_tokens()
    {
        $tokens = get_option(self::TOKEN_OPTION, array());
        return is_array($tokens) ? $tokens : array();
    }

    public function update_tokens(array $tokens)
    {
        update_option(self::TOKEN_OPTION, $tokens, false);
    }

    public function clear_tokens()
    {
        delete_option(self::TOKEN_OPTION);
    }

    /**
     * OAuth redirect URI registered with Google Cloud (must match exactly).
     */
    public function redirect_uri()
    {
        return admin_url('admin-ajax.php?action=autodocs_google_oauth_callback');
    }

    /**
     * Stable hash of current OAuth client credentials (for detecting mismatched stored tokens).
     */
    public function credentials_fingerprint()
    {
        $id = (string) $this->get('client_id', '');
        $secret = (string) $this->get('client_secret', '');

        if ($id === '' || $secret === '') {
            return '';
        }

        return hash('sha256', $id . '|' . $secret);
    }
}
