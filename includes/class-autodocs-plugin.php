<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-settings.php';
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

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
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

        add_action(self::CRON_HOOK, array($this->sync_service, 'sync_all_configured_folders'));
    }
}