<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers and computes WordPress cron schedules for automatic sync.
 */
final class AutoDocs_Cron_Schedule
{
    public const SCHEDULE_15MIN = 'autodocs_every_15_minutes';
    public const SCHEDULE_30MIN = 'autodocs_every_30_minutes';
    public const SCHEDULE_6HOURS = 'autodocs_every_6_hours';

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
        $schedules[self::SCHEDULE_6HOURS] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 hours', 'autodocs-publisher'),
        );

        return $schedules;
    }

    /**
     * @return array<string, string> Setting key => WP cron schedule name.
     */
    public static function interval_choices()
    {
        return array(
            '15min' => self::SCHEDULE_15MIN,
            '30min' => self::SCHEDULE_30MIN,
            'hourly' => 'hourly',
            '6hours' => self::SCHEDULE_6HOURS,
            'twicedaily' => 'twicedaily',
            'daily' => 'daily',
        );
    }

    /**
     * @return array<string, string>
     */
    public static function interval_labels()
    {
        return array(
            '15min' => __('Every 15 minutes', 'autodocs-publisher'),
            '30min' => __('Every 30 minutes', 'autodocs-publisher'),
            'hourly' => __('Every hour', 'autodocs-publisher'),
            '6hours' => __('Every 6 hours', 'autodocs-publisher'),
            'twicedaily' => __('Twice daily', 'autodocs-publisher'),
            'daily' => __('Once daily', 'autodocs-publisher'),
        );
    }

    /**
     * @param string $setting_value
     * @return string
     */
    public static function wp_schedule_for_setting($setting_value)
    {
        $map = self::interval_choices();
        $key = is_string($setting_value) ? $setting_value : '';

        return isset($map[ $key ]) ? $map[ $key ] : 'hourly';
    }

    /**
     * @param string $raw HH:MM
     * @return array{hour: int, minute: int}
     */
    public static function parse_time_of_day($raw)
    {
        $raw = trim((string) $raw);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
            return array(
                'hour' => max(0, min(23, (int) $m[1])),
                'minute' => max(0, min(59, (int) $m[2])),
            );
        }

        return array('hour' => 3, 'minute' => 0);
    }

    /**
     * @param string $wp_schedule
     * @return int
     */
    public static function interval_seconds($wp_schedule)
    {
        $schedules = wp_get_schedules();
        if (isset($schedules[ $wp_schedule ]['interval'])) {
            return max(60, (int) $schedules[ $wp_schedule ]['interval']);
        }

        return HOUR_IN_SECONDS;
    }

    /**
     * @param int $hour
     * @param int $minute
     * @param int $from_ts
     * @return int
     */
    public static function next_timestamp_for_time_of_day($hour, $minute, $from_ts)
    {
        $tz = wp_timezone();
        $dt = (new DateTimeImmutable('@' . $from_ts))->setTimezone($tz)->setTime($hour, $minute, 0);
        if ($dt->getTimestamp() <= $from_ts) {
            $dt = $dt->modify('+1 day');
        }

        return $dt->getTimestamp();
    }

    /**
     * @param AutoDocs_Settings $settings
     * @param bool              $prefer_soon First run ~1 minute after save.
     * @return int
     */
    public static function first_run_timestamp(AutoDocs_Settings $settings, $prefer_soon = false)
    {
        $interval = (string) $settings->get('cron_interval', 'hourly');
        $time = self::parse_time_of_day((string) $settings->get('cron_time', '03:00'));
        $now = time();

        if (self::is_daily_interval($interval)) {
            return self::next_timestamp_for_time_of_day($time['hour'], $time['minute'], $now);
        }

        if ($prefer_soon) {
            return $now + MINUTE_IN_SECONDS;
        }

        return $now + self::interval_seconds(self::wp_schedule_for_setting($interval));
    }

    /**
     * @param string $interval
     * @param string $cron_time
     * @param bool   $enabled
     * @return int
     */
    public static function preview_timestamp($interval, $cron_time, $enabled)
    {
        if (! $enabled) {
            return 0;
        }

        $interval = is_string($interval) ? $interval : 'hourly';
        $time = self::parse_time_of_day($cron_time);
        $now = time();

        if (self::is_daily_interval($interval)) {
            return self::next_timestamp_for_time_of_day($time['hour'], $time['minute'], $now);
        }

        return $now + MINUTE_IN_SECONDS;
    }

    /**
     * @param string $interval
     * @return bool
     */
    public static function is_daily_interval($interval)
    {
        return in_array($interval, array('daily', 'twicedaily'), true);
    }

    /**
     * @param AutoDocs_Settings|null $settings
     * @param bool                   $prefer_soon
     */
    public static function reschedule($settings = null, $prefer_soon = false)
    {
        if (! $settings instanceof AutoDocs_Settings) {
            $settings = new AutoDocs_Settings();
        }

        wp_clear_scheduled_hook(AutoDocs_Plugin::CRON_HOOK);

        if ($settings->get('cron_enabled', '') !== '1') {
            return;
        }

        $schedule = self::wp_schedule_for_setting((string) $settings->get('cron_interval', 'hourly'));
        $first = self::first_run_timestamp($settings, $prefer_soon);
        wp_schedule_event($first, $schedule, AutoDocs_Plugin::CRON_HOOK);
    }

    /**
     * @return int
     */
    public static function next_run_timestamp()
    {
        $ts = wp_next_scheduled(AutoDocs_Plugin::CRON_HOOK);

        return $ts ? (int) $ts : 0;
    }
}
