<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-settings.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-cron.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-acf-helpers.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-google-client.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-sync-service.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-admin.php';

final class AutoDocs_Plugin
{
    const CRON_HOOK = 'autodocs_publisher_scheduled_sync';

    private static $instance = null;

    private $settings;
    private $google_client;
    private $sync_service;
    private $admin;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate()
    {
        AutoDocs_Settings::add_defaults();
        AutoDocs_Cron::reschedule(null, true);
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private function __construct()
    {
        $this->settings = new AutoDocs_Settings();
        $this->google_client = new AutoDocs_Google_Client($this->settings);
        $this->sync_service = new AutoDocs_Sync_Service($this->settings, $this->google_client);
        $this->admin = new AutoDocs_Admin($this->settings, $this->google_client, $this->sync_service);

        add_filter('cron_schedules', array('AutoDocs_Cron', 'add_schedules'));
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_cron'));
        add_action('update_option_' . AutoDocs_Settings::OPTION_NAME, array($this, 'reschedule_cron_after_settings_save'), 10, 2);
        add_action('init', array($this, 'maybe_ensure_cron_scheduled'));
        add_action('init', array($this, 'maybe_run_due_cron_on_request'), 20);
        add_action('load-settings_page_autodocs-publisher', array($this, 'maybe_run_due_cron_on_settings_screen'));
    }

    /**
     * Run automatic sync when it is due and someone visits the site (front or admin).
     */
    public function maybe_run_due_cron_on_request()
    {
        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        AutoDocs_Cron::maybe_run_due_on_request($this->sync_service, $this->google_client, $this->settings);
    }

    public function maybe_ensure_cron_scheduled()
    {
        if ($this->settings->get('cron_enabled', '') !== '1') {
            return;
        }
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            AutoDocs_Cron::reschedule($this->settings, true);
        }
    }

    /**
     * Run overdue automatic sync when viewing the plugin settings screen (helps local / low-traffic sites).
     */
    public function maybe_run_due_cron_on_settings_screen()
    {
        AutoDocs_Cron::run_due_sync($this->sync_service, $this->google_client, $this->settings);
    }

    public function run_scheduled_cron()
    {
        AutoDocs_Cron::run_scheduled_sync($this->sync_service, $this->google_client, $this->settings);
    }

    /**
     * @param mixed $old_value
     * @param mixed $value
     */
    public function reschedule_cron_after_settings_save($old_value, $value)
    {
        AutoDocs_Cron::reschedule($this->settings, true);
    }
}