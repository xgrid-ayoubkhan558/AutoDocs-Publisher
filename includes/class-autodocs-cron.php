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
     * Setting key => WP cron schedule name.
     *
     * @return array<string, string>
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
     * @return array<string, string> Setting key => translated label.
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
     * @return string WP cron schedule name.
     */
    public static function wp_schedule_for_setting($setting_value)
    {
        $map = self::interval_choices();
        $key = is_string($setting_value) ? $setting_value : '';

        return isset($map[$key]) ? $map[$key] : 'hourly';
    }

    /**
     * @param string $raw HH:MM or H:MM
     * @return array{hour: int, minute: int}
     */
    public static function parse_cron_time($raw)
    {
        $raw = trim((string) $raw);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
            $hour = max(0, min(23, (int) $m[1]));
            $minute = max(0, min(59, (int) $m[2]));

            return array('hour' => $hour, 'minute' => $minute);
        }

        return array('hour' => 3, 'minute' => 0);
    }

    /**
     * @param int $hour
     * @param int $minute
     * @param int $from_ts Unix timestamp.
     * @return int
     */
    public static function next_timestamp_for_clock_time($hour, $minute, $from_ts)
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
     * @return int Unix timestamp for wp_schedule_event.
     */
    public static function compute_first_run_timestamp(AutoDocs_Settings $settings)
    {
        $interval = (string) $settings->get('cron_interval', 'hourly');
        $time = self::parse_cron_time((string) $settings->get('cron_time', '03:00'));
        $now = time();

        if (in_array($interval, array('daily', 'twicedaily'), true)) {
            return self::next_timestamp_for_clock_time($time['hour'], $time['minute'], $now);
        }

        return $now + MINUTE_IN_SECONDS;
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
        $first = self::compute_first_run_timestamp($settings);
        wp_schedule_event($first, $schedule, AutoDocs_Plugin::CRON_HOOK);
    }

    /**
     * Human-readable explanation of the current schedule (for settings UI).
     *
     * @param AutoDocs_Settings|null $settings
     * @return string
     */
    public static function schedule_description($settings = null)
    {
        if (! $settings instanceof AutoDocs_Settings) {
            $settings = new AutoDocs_Settings();
        }

        if ($settings->get('cron_enabled', '') !== '1') {
            return __('Automatic sync is disabled.', 'autodocs-publisher');
        }

        $interval = (string) $settings->get('cron_interval', 'hourly');
        $labels = self::interval_labels();
        $label = isset($labels[$interval]) ? $labels[$interval] : $labels['hourly'];
        $time = self::parse_cron_time((string) $settings->get('cron_time', '03:00'));
        $time_fmt = sprintf('%02d:%02d', $time['hour'], $time['minute']);
        $tz = wp_timezone_string();
        $tz_label = $tz !== '' ? $tz : __('site time', 'autodocs-publisher');

        $parts = array();

        if ($interval === 'daily') {
            $parts[] = sprintf(
                /* translators: 1: time HH:MM, 2: timezone */
                __('Runs once per day at %1$s (%2$s).', 'autodocs-publisher'),
                $time_fmt,
                $tz_label
            );
        } elseif ($interval === 'twicedaily') {
            $second = self::parse_cron_time(sprintf('%02d:%02d', ($time['hour'] + 12) % 24, $time['minute']));
            $parts[] = sprintf(
                /* translators: 1: first time, 2: second time, 3: timezone */
                __('Runs twice daily at %1$s and %2$s (%3$s).', 'autodocs-publisher'),
                $time_fmt,
                sprintf('%02d:%02d', $second['hour'], $second['minute']),
                $tz_label
            );
        } elseif (in_array($interval, array('15min', '30min', 'hourly', '6hours'), true)) {
            $parts[] = sprintf(
                /* translators: %s: interval label e.g. "Every hour" */
                __('Runs %s (starting soon after you save). The clock time below applies only to daily schedules.', 'autodocs-publisher'),
                strtolower($label)
            );
        } else {
            $parts[] = $label;
        }

        $next = self::next_run_formatted();
        if ($next !== '') {
            $parts[] = sprintf(
                /* translators: %s: formatted date/time */
                __('Next scheduled run: %s.', 'autodocs-publisher'),
                $next
            );
        }

        $parts[] = __('WordPress runs scheduled tasks when your site receives visits or when a server cron hits wp-cron.php.', 'autodocs-publisher');

        return implode(' ', $parts);
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
        $sync_service->sync_all(false, AutoDocs_Sync_Meta::SYNC_SOURCE_CRON);
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
     * @return int
     */
    public static function next_run_timestamp()
    {
        $ts = wp_next_scheduled(AutoDocs_Plugin::CRON_HOOK);

        return $ts ? (int) $ts : 0;
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
