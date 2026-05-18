<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/admin/class-autodocs-admin-localization.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/admin/trait-autodocs-admin-list-table.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/admin/trait-autodocs-admin-oauth.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/admin/trait-autodocs-admin-notices.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/admin/trait-autodocs-admin-ajax.php';

class AutoDocs_Admin
{
    use AutoDocs_Admin_List_Table_Trait;
    use AutoDocs_Admin_Oauth_Trait;
    use AutoDocs_Admin_Notices_Trait;
    use AutoDocs_Admin_Ajax_Trait;

    private $settings;
    private $google_client;
    private $sync_service;

    public function __construct(AutoDocs_Settings $settings, AutoDocs_Google_Client $google_client, AutoDocs_Sync_Service $sync_service)
    {
        $this->settings = $settings;
        $this->google_client = $google_client;
        $this->sync_service = $sync_service;

        add_action('admin_init', array($this->settings, 'register'));
        add_action('admin_init', array($this, 'maybe_redirect_google_oauth_start'), 1);
        add_action('admin_menu', array($this, 'menu'));
        add_action('load-settings_page_autodocs-publisher', array($this, 'enqueue_settings_assets'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('admin_post_autodocs_google_disconnect', array($this, 'handle_post_google_disconnect'));
        add_action('wp_ajax_autodocs_sync_now', array($this, 'ajax_sync_now'));
        add_action('wp_ajax_autodocs_refresh_statuses', array($this, 'ajax_refresh_statuses'));
        add_action('wp_ajax_autodocs_list_drive_folders', array($this, 'ajax_list_drive_folders'));
        add_action('wp_ajax_autodocs_list_bucket_articles', array($this, 'ajax_list_bucket_articles'));
        add_action('wp_ajax_autodocs_drive_item_meta', array($this, 'ajax_drive_item_meta'));
        add_action('wp_ajax_autodocs_sidebar_snapshot', array($this, 'ajax_sidebar_snapshot'));
        add_action('wp_ajax_autodocs_prepare_import_new', array($this, 'ajax_prepare_import_new'));
        add_action('wp_ajax_autodocs_import_acf_body_choices', array($this, 'ajax_import_acf_body_choices'));
        add_action('wp_ajax_autodocs_import_new_folder', array($this, 'ajax_import_new_folder'));
        add_action('wp_ajax_autodocs_bulk_import_folders', array($this, 'ajax_bulk_import_folders'));
        add_action('wp_ajax_autodocs_google_oauth_callback', array($this, 'ajax_google_oauth_callback'));
        add_action('wp_ajax_autodocs_cron_preview', array($this, 'ajax_cron_preview'));
        add_action('wp_ajax_nopriv_autodocs_google_oauth_callback', array($this, 'ajax_google_oauth_callback'));

        foreach (get_post_types(array('show_ui' => true), 'names') as $post_type) {
            add_filter('manage_' . $post_type . '_posts_columns', array($this, 'columns'));
            add_action('manage_' . $post_type . '_posts_custom_column', array($this, 'column_content'), 10, 2);
        }
        add_action('restrict_manage_posts', array($this, 'status_filter'));
        add_action('pre_get_posts', array($this, 'apply_status_filter'));
        add_filter('plugin_action_links_' . plugin_basename(AUTODOCS_PUBLISHER_FILE), array($this, 'plugin_action_links'), 10, 1);
        add_filter('pre_update_option_' . AutoDocs_Settings::OPTION_NAME, array($this, 'validate_drive_buckets_under_root'), 10, 2);
    }

    /**
     * Ensure New / Synced folder ids are immediate children of the saved Drive root.
     *
     * @param array<string, mixed> $value
     * @param array<string, mixed> $old_value
     * @return array<string, mixed>
     */
    public function validate_drive_buckets_under_root($value, $old_value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $old_value = is_array($old_value) ? $old_value : array();

        $root = isset($value['working_folder_id']) ? trim((string) $value['working_folder_id']) : '';
        if ($root === '') {
            $value['folder_new'] = '';
            $value['folder_synced'] = '';
            $value['folder_modified'] = '';

            return $value;
        }

        if (! $this->google_client->is_connected()) {
            return $value;
        }

        $children = $this->google_client->list_folders($root);
        if (is_wp_error($children) || ! is_array($children)) {
            return $value;
        }

        $allowed = array();
        foreach ($children as $row) {
            if (! empty($row['id'])) {
                $allowed[] = (string) $row['id'];
            }
        }

        foreach (array('folder_new', 'folder_synced') as $key) {
            $id = isset($value[$key]) ? trim((string) $value[$key]) : '';
            if ($id !== '' && ! in_array($id, $allowed, true)) {
                $value[$key] = isset($old_value[$key]) ? (string) $old_value[$key] : '';
                if ($value[$key] !== '' && ! in_array($value[$key], $allowed, true)) {
                    $value[$key] = '';
                }
            }
        }

        return $value;
    }

    public function menu()
    {
        add_options_page(
            __('AutoDocs Publisher', 'autodocs-publisher'),
            __('AutoDocs Publisher', 'autodocs-publisher'),
            'manage_options',
            'autodocs-publisher',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Reliable enqueue on the plugin settings screen (admin_enqueue_scripts $hook can vary).
     */
    public function enqueue_settings_assets()
    {
        $this->enqueue_admin_scripts();
        wp_localize_script(
            'autodocs-admin-init',
            'AutoDocsDashboard',
            AutoDocs_Admin_Localization::dashboard_config($this->settings, $this->google_client)
        );
    }

    public function assets($hook)
    {
        if ('edit.php' === $hook || 'settings_page_autodocs-publisher' === $hook) {
            $this->enqueue_admin_scripts();
        }
    }

    private function enqueue_admin_scripts()
    {
        $url = AUTODOCS_PUBLISHER_URL;
        $ver = AUTODOCS_PUBLISHER_VERSION;

        wp_enqueue_style('dashicons');
        wp_enqueue_style('autodocs-admin', $url . 'assets/admin.css', array('dashicons'), $ver);

        wp_register_script('autodocs-admin-core', $url . 'assets/js/autodocs-admin-core.js', array(), $ver, true);
        wp_register_script('autodocs-admin-format', $url . 'assets/js/autodocs-admin-format.js', array('autodocs-admin-core'), $ver, true);
        wp_register_script('autodocs-admin-sidebar', $url . 'assets/js/autodocs-admin-sidebar.js', array('autodocs-admin-core', 'autodocs-admin-format'), $ver, true);
        wp_register_script('autodocs-admin-drive-picker', $url . 'assets/js/autodocs-admin-drive-picker.js', array('autodocs-admin-core', 'autodocs-admin-format'), $ver, true);
        wp_register_script('autodocs-admin-import-form', $url . 'assets/js/autodocs-admin-import-form.js', array('autodocs-admin-core', 'autodocs-admin-format'), $ver, true);
        wp_register_script(
            'autodocs-admin-import-wizard',
            $url . 'assets/js/autodocs-admin-import-wizard.js',
            array('autodocs-admin-core', 'autodocs-admin-format'),
            $ver,
            true
        );
        wp_register_script(
            'autodocs-admin-bucket-ui',
            $url . 'assets/js/autodocs-admin-bucket-ui.js',
            array('autodocs-admin-core', 'autodocs-admin-format', 'autodocs-admin-sidebar', 'autodocs-admin-drive-picker', 'autodocs-admin-import-wizard'),
            $ver,
            true
        );
        wp_register_script('autodocs-admin-init', $url . 'assets/js/autodocs-admin-init.js', array('autodocs-admin-bucket-ui'), $ver, true);

        wp_enqueue_script('autodocs-admin-init');

        // `load-settings_page_*` and `admin_enqueue_scripts` both enqueue on the settings screen;
        // localize Publisher only once to avoid duplicate inline `AutoDocsPublisher` output.
        static $publisher_script_data_printed = false;
        if (! $publisher_script_data_printed) {
            $publisher_script_data_printed = true;
            wp_localize_script(
                'autodocs-admin-init',
                'AutoDocsPublisher',
                AutoDocs_Admin_Localization::publisher_config($this->settings)
            );
        }
    }

    public function render_settings_page()
    {
        $settings = $this->settings->all();
        $connected = $this->google_client->is_connected();
        $client_id = isset($settings['client_id']) ? (string) $settings['client_id'] : '';
        $client_secret = isset($settings['client_secret']) ? (string) $settings['client_secret'] : '';
        $has_saved_creds = ($client_id !== '' && $client_secret !== '');
        $oauth_start_url = '';
        if ($has_saved_creds && ! $connected) {
            $oauth_start_url = wp_nonce_url(
                add_query_arg(
                    array(
                        'page' => 'autodocs-publisher',
                        'autodocs_oauth_go' => '1',
                    ),
                    admin_url('options-general.php')
                ),
                'autodocs_oauth_go'
            );
        }

        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'articles';
        if ('general' === $current_tab) {
            $current_tab = 'settings';
        }
        if (! in_array($current_tab, array('articles', 'drive', 'settings'), true)) {
            $current_tab = 'articles';
        }
        $drive_root = isset($settings['working_folder_id']) ? trim((string) $settings['working_folder_id']) : '';
        $fn = isset($settings['folder_new']) ? (string) $settings['folder_new'] : '';
        $fs = isset($settings['folder_synced']) ? (string) $settings['folder_synced'] : '';
        $root_name = isset($settings['working_folder_name']) ? (string) $settings['working_folder_name'] : '';
        $acf_body_field = isset($settings['acf_body_field']) ? (string) $settings['acf_body_field'] : '';
        $acf_body_field_choices = AutoDocs_Acf_Helpers::list_body_target_fields();
        $acf_choice_values = AutoDocs_Acf_Helpers::choice_values($acf_body_field_choices);
        $acf_body_use_custom = ($acf_body_field !== '' && ! in_array($acf_body_field, $acf_choice_values, true));
        $acf_select_value = $acf_body_use_custom ? AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE : $acf_body_field;

        $cron_enabled = isset($settings['cron_enabled']) && $settings['cron_enabled'] === '1';
        $cron_interval = isset($settings['cron_interval']) ? (string) $settings['cron_interval'] : 'hourly';
        $cron_time = isset($settings['cron_time']) ? (string) $settings['cron_time'] : '03:00';
        $cron_next_run = $cron_enabled ? AutoDocs_Cron::next_run_formatted() : '';
        $cron_last_run = AutoDocs_Cron::last_run_formatted();
        $cron_schedule_description = AutoDocs_Cron::schedule_description($this->settings);
        $cron_timezone_label = AutoDocs_Cron::timezone_label();
        $cron_site_time_now = AutoDocs_Cron::site_now_formatted();
        $cron_show_time = in_array($cron_interval, array('daily', 'twicedaily'), true);
        $cron_general_settings_url = admin_url('options-general.php');
        $recent_syncs = $this->sync_service->list_recent_synced_posts(12);

        include AUTODOCS_PUBLISHER_DIR . 'includes/admin/views/settings-page.php';
    }
}
