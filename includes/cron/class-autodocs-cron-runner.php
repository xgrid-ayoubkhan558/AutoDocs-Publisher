<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Executes automatic sync when due and records last run time.
 */
final class AutoDocs_Cron_Runner
{
    public const LOCK_TRANSIENT = 'autodocs_cron_running';

    /**
     * Run when overdue on any front-end or admin request.
     *
     * @return bool
     */
    public static function maybe_run_on_request($sync_service, $google_client, $settings)
    {
        if ($settings->get('cron_enabled', '') !== '1') {
            return false;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            return false;
        }

        $timestamp = wp_next_scheduled(AutoDocs_Plugin::CRON_HOOK);
        if (! $timestamp || $timestamp > time()) {
            return false;
        }

        return self::run_due($sync_service, $google_client, $settings);
    }

    /**
     * @return bool
     */
    public static function run_due($sync_service, $google_client, $settings)
    {
        if ($settings->get('cron_enabled', '') !== '1' || get_transient(self::LOCK_TRANSIENT)) {
            return false;
        }

        $timestamp = wp_next_scheduled(AutoDocs_Plugin::CRON_HOOK);
        if (! $timestamp || $timestamp > time()) {
            return false;
        }

        wp_clear_scheduled_hook(AutoDocs_Plugin::CRON_HOOK);
        self::execute($sync_service, $google_client, $settings);
        AutoDocs_Cron_Schedule::reschedule($settings, false);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function status_payload($sync_service, $google_client, $settings)
    {
        if (! defined('DISABLE_WP_CRON') || ! DISABLE_WP_CRON) {
            spawn_cron();
        }

        $ran_now = self::run_due($sync_service, $google_client, $settings);
        $last_raw = (string) get_option(AutoDocs_Sync_Meta::OPTION_LAST_CRON_RUN, '');
        $last_ts = AutoDocs_Sync_Datetime::mysql_to_timestamp($last_raw);

        return array(
            'next_run_ts' => AutoDocs_Cron_Schedule::next_run_timestamp(),
            'last_run' => $last_ts > 0 ? AutoDocs_Sync_Datetime::format_with_timezone($last_ts) : '',
            'last_run_ts' => $last_ts,
            'ran_now' => $ran_now,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        );
    }

    /**
     * Import new bucket articles, update synced posts, record last run.
     */
    public static function execute($sync_service, $google_client, $settings)
    {
        if ($settings->get('cron_enabled', '') !== '1') {
            return;
        }

        if (! $google_client->is_connected() || $google_client->tokens_need_reconnect_for_scopes()) {
            return;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            return;
        }

        set_transient(self::LOCK_TRANSIENT, (string) time(), 10 * MINUTE_IN_SECONDS);

        try {
            $sync_service->refresh_known_statuses();
            $sync_service->import_new_bucket_folders_for_cron(10);
            $sync_service->sync_all(false, AutoDocs_Sync_Meta::SYNC_SOURCE_CRON);
            update_option(AutoDocs_Sync_Meta::OPTION_LAST_CRON_RUN, current_time('mysql'), false);
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }
}
