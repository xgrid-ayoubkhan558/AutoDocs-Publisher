<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/helpers/class-autodocs-sync-datetime.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/cron/class-autodocs-cron-timezone.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/cron/class-autodocs-cron-schedule.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/cron/class-autodocs-cron-runner.php';

/**
 * Public API for scheduled automatic sync (facade over schedule + runner).
 */
final class AutoDocs_Cron
{
    /** @deprecated Use AutoDocs_Cron_Runner::LOCK_TRANSIENT */
    public const LOCK_TRANSIENT = AutoDocs_Cron_Runner::LOCK_TRANSIENT;

    public const SCHEDULE_15MIN = AutoDocs_Cron_Schedule::SCHEDULE_15MIN;
    public const SCHEDULE_30MIN = AutoDocs_Cron_Schedule::SCHEDULE_30MIN;
    public const SCHEDULE_6HOURS = AutoDocs_Cron_Schedule::SCHEDULE_6HOURS;

    public static function add_schedules(array $schedules)
    {
        return AutoDocs_Cron_Schedule::add_schedules($schedules);
    }

    public static function interval_choices()
    {
        return AutoDocs_Cron_Schedule::interval_choices();
    }

    public static function interval_labels()
    {
        return AutoDocs_Cron_Schedule::interval_labels();
    }

    public static function wp_schedule_for_setting($setting_value)
    {
        return AutoDocs_Cron_Schedule::wp_schedule_for_setting($setting_value);
    }

    public static function timezone_label()
    {
        return AutoDocs_Cron_Timezone::label();
    }

    public static function timezone_for_intl()
    {
        return AutoDocs_Cron_Timezone::for_intl();
    }

    public static function site_gmt_offset_hours()
    {
        return AutoDocs_Cron_Timezone::gmt_offset_hours();
    }

    public static function format_timestamp($timestamp)
    {
        return AutoDocs_Sync_Datetime::format_with_timezone($timestamp);
    }

    public static function site_now_formatted()
    {
        return AutoDocs_Sync_Datetime::site_now_with_timezone();
    }

    public static function relative_until_formatted($timestamp)
    {
        return AutoDocs_Sync_Datetime::relative_to_now($timestamp);
    }

    public static function interval_repeat_label($interval)
    {
        $labels = self::interval_labels();
        $key = is_string($interval) ? $interval : '';
        if ($key === '' || ! isset($labels[ $key ])) {
            return '';
        }

        return sprintf(
            __('Then %s.', 'autodocs-publisher'),
            strtolower($labels[ $key ])
        );
    }

    public static function parse_cron_time($raw)
    {
        return AutoDocs_Cron_Schedule::parse_time_of_day($raw);
    }

    public static function schedule_interval_seconds($wp_schedule)
    {
        return AutoDocs_Cron_Schedule::interval_seconds($wp_schedule);
    }

    public static function compute_first_run_timestamp(AutoDocs_Settings $settings, $prefer_soon = false)
    {
        return AutoDocs_Cron_Schedule::first_run_timestamp($settings, $prefer_soon);
    }

    public static function preview_next_timestamp($interval, $cron_time, $enabled)
    {
        return AutoDocs_Cron_Schedule::preview_timestamp($interval, $cron_time, $enabled);
    }

    public static function reschedule($settings = null, $prefer_soon = false)
    {
        AutoDocs_Cron_Schedule::reschedule($settings, $prefer_soon);
    }

    public static function maybe_run_due_on_request($sync_service, $google_client, $settings)
    {
        return AutoDocs_Cron_Runner::maybe_run_on_request($sync_service, $google_client, $settings);
    }

    public static function run_due_sync($sync_service, $google_client, $settings)
    {
        return AutoDocs_Cron_Runner::run_due($sync_service, $google_client, $settings);
    }

    public static function status_payload($sync_service, $google_client, $settings)
    {
        return AutoDocs_Cron_Runner::status_payload($sync_service, $google_client, $settings);
    }

    public static function run_scheduled_sync($sync_service, $google_client, $settings)
    {
        AutoDocs_Cron_Runner::execute($sync_service, $google_client, $settings);
    }

    public static function next_run_timestamp()
    {
        return AutoDocs_Cron_Schedule::next_run_timestamp();
    }

    public static function next_run_formatted()
    {
        $ts = self::next_run_timestamp();

        return $ts > 0 ? self::format_timestamp($ts) : '';
    }

    public static function last_run_formatted()
    {
        $last_ts = AutoDocs_Sync_Datetime::mysql_to_timestamp(
            (string) get_option(AutoDocs_Sync_Meta::OPTION_LAST_CRON_RUN, '')
        );

        return $last_ts > 0 ? self::format_timestamp($last_ts) : '';
    }

    /** @deprecated UI helper; kept for compatibility. */
    public static function schedule_description($settings = null)
    {
        return '';
    }

    /** @deprecated UI helper; kept for compatibility. */
    public static function format_clock_time($hour, $minute)
    {
        $tz = wp_timezone();
        $dt = (new DateTimeImmutable('now', $tz))->setTime($hour, $minute, 0);

        return wp_date(get_option('time_format'), $dt->getTimestamp());
    }

    /** @deprecated */
    public static function next_timestamp_for_clock_time($hour, $minute, $from_ts)
    {
        return AutoDocs_Cron_Schedule::next_timestamp_for_time_of_day($hour, $minute, $from_ts);
    }
}
