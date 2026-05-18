<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Scheduled automatic sync (refresh modified status + update synced posts).
 */
final class AutoDocs_Cron
{
    public const SCHEDULE_15MIN = 'autodocs_every_15_minutes';
    public const SCHEDULE_30MIN = 'autodocs_every_30_minutes';

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public static function add_schedules(array $schedules)
    {
        $schedules[self::SCHEDULE_15MIN] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 minutes', 'autodocs-publisher'),
        );
        $schedules[self::SCHEDULE_30MIN] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 minutes', 'autodocs-publisher'),
        );

        return $schedules;
    }

    /**
     * @return array<string, string>
     */
    public static function interval_choices()
    {
        return array(
            '15min' => self::SCHEDULE_15MIN,
            '30min' => self::SCHEDULE_30MIN,
            'hourly' => 'hourly',
            'twicedaily' => 'twicedaily',
            'daily' => 'daily',
        );
    }

    /**
     * @param string $setting_value
     * @return string WP cron schedule name.
     */
    public static function wp_schedule_for_setting($setting_value)
    {
        $map = self::interval_choices();
        $key = is_string($setting_value) ? $setting_value : '';

        return isset($map[$key]) ? $map[$key] : 'hourly';
    }

    /**
     * @param AutoDocs_Settings|null $settings
     */
    public static function reschedule($settings = null)
    {
        if (! $settings instanceof AutoDocs_Settings) {
            $settings = new AutoDocs_Settings();
        }

        wp_clear_scheduled_hook(AutoDocs_Plugin::CRON_HOOK);

        if ($settings->get('cron_enabled', '') !== '1') {
            return;
        }

        $schedule = self::wp_schedule_for_setting((string) $settings->get('cron_interval', 'hourly'));
        wp_schedule_event(time() + MINUTE_IN_SECONDS, $schedule, AutoDocs_Plugin::CRON_HOOK);
    }

    /**
     * @param AutoDocs_Sync_Service $sync_service
     * @param AutoDocs_Google_Client $google_client
     * @param AutoDocs_Settings $settings
     */
    public static function run_scheduled_sync($sync_service, $google_client, $settings)
    {
        if ($settings->get('cron_enabled', '') !== '1') {
            return;
        }

        if (! $google_client->is_connected() || $google_client->tokens_need_reconnect_for_scopes()) {
            return;
        }

        update_option(AutoDocs_Sync_Meta::OPTION_LAST_CRON_RUN, current_time('mysql'), false);

        $sync_service->refresh_known_statuses();
        $sync_service->sync_all(false);
    }

    /**
     * @return string
     */
    public static function next_run_formatted()
    {
        $ts = wp_next_scheduled(AutoDocs_Plugin::CRON_HOOK);
        if (! $ts) {
            return '';
        }

        return (string) wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $ts
        );
    }

    /**
     * @return string
     */
    public static function last_run_formatted()
    {
        $raw = (string) get_option(AutoDocs_Sync_Meta::OPTION_LAST_CRON_RUN, '');
        if ($raw === '') {
            return '';
        }

        return (string) mysql2date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $raw
        );
    }
}
