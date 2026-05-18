<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Scheduled automatic sync (refresh modified status + update synced posts).
 */
final class AutoDocs_Cron
{
    public const LOCK_TRANSIENT = 'autodocs_cron_running';

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
     * Friendly timezone label (city name or UTC offset).
     *
     * @return string
     */
    public static function timezone_label()
    {
        $tz_string = (string) get_option('timezone_string', '');
        if ($tz_string !== '') {
            try {
                $tz = new DateTimeZone($tz_string);
                $now = new DateTimeImmutable('now', $tz);

                return sprintf('%s (%s)', $tz_string, $now->format('P'));
            } catch (Exception $e) {
                return $tz_string;
            }
        }

        $offset = (float) get_option('gmt_offset', 0);
        if (0.0 === $offset) {
            return __('UTC', 'autodocs-publisher');
        }

        $sign = $offset >= 0 ? '+' : '-';
        $abs = abs($offset);
        $hours = (int) floor($abs);
        $minutes = (int) round(($abs - $hours) * 60);

        return sprintf('UTC%s%d:%02d', $sign, $hours, $minutes);
    }

    /**
     * IANA timezone for JavaScript Intl (maps offset-only WP installs).
     *
     * @return string
     */
    public static function timezone_for_intl()
    {
        $tz_string = (string) get_option('timezone_string', '');
        if ($tz_string !== '' && false !== strpos($tz_string, '/')) {
            return $tz_string;
        }

        $offset = (float) get_option('gmt_offset', 0);
        if (0.0 === $offset) {
            return 'UTC';
        }

        $hours = (int) abs($offset);
        $sign = $offset > 0 ? '-' : '+';

        return 'Etc/GMT' . $sign . $hours;
    }

    /**
     * @return float Hours east of UTC (WordPress gmt_offset).
     */
    public static function site_gmt_offset_hours()
    {
        return (float) get_option('gmt_offset', 0);
    }

    /**
     * @param int $timestamp Unix timestamp (UTC).
     * @return string
     */
    public static function format_timestamp($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        return sprintf(
            '%s (%s)',
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
            self::timezone_label()
        );
    }

    /**
     * @return string Current clock time on the WordPress site.
     */
    public static function site_now_formatted()
    {
        return wp_date(get_option('time_format'), time()) . ' (' . self::timezone_label() . ')';
    }

    /**
     * @param int $timestamp Unix timestamp (UTC).
     * @return string e.g. "in 5 minutes" or "2 hours ago".
     */
    public static function relative_until_formatted($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $now = time();
        if ($timestamp <= $now) {
            return sprintf(
                /* translators: %s: human time diff e.g. "5 minutes" */
                __('%s ago', 'autodocs-publisher'),
                human_time_diff($timestamp, $now, true)
            );
        }

        return sprintf(
            /* translators: %s: human time diff e.g. "5 minutes" */
            __('in %s', 'autodocs-publisher'),
            human_time_diff($now, $timestamp, true)
        );
    }

    /**
     * @param string $interval Setting key (15min, hourly, …).
     * @return string
     */
    public static function interval_repeat_label($interval)
    {
        $labels = self::interval_labels();
        $key = is_string($interval) ? $interval : '';
        if ($key === '' || ! isset($labels[$key])) {
            return '';
        }

        return sprintf(
            /* translators: %s: interval label e.g. "every 15 minutes" */
            __('Then %s.', 'autodocs-publisher'),
            strtolower($labels[$key])
        );
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
     * @param string $wp_schedule WP cron schedule slug.
     * @return int Seconds between runs.
     */
    public static function schedule_interval_seconds($wp_schedule)
    {
        $schedules = wp_get_schedules();
        if (isset($schedules[$wp_schedule]['interval'])) {
            return max(60, (int) $schedules[$wp_schedule]['interval']);
        }

        return HOUR_IN_SECONDS;
    }

    /**
     * @param AutoDocs_Settings $settings
     * @param bool              $prefer_soon If true, first run ~1 minute from now (after saving settings).
     * @return int Unix timestamp for wp_schedule_event.
     */
    public static function compute_first_run_timestamp(AutoDocs_Settings $settings, $prefer_soon = false)
    {
        $interval = (string) $settings->get('cron_interval', 'hourly');
        $time = self::parse_cron_time((string) $settings->get('cron_time', '03:00'));
        $now = time();

        if (in_array($interval, array('daily', 'twicedaily'), true)) {
            return self::next_timestamp_for_clock_time($time['hour'], $time['minute'], $now);
        }

        if ($prefer_soon) {
            return $now + MINUTE_IN_SECONDS;
        }

        $schedule = self::wp_schedule_for_setting($interval);

        return $now + self::schedule_interval_seconds($schedule);
    }

    /**
     * Estimated next run for unsaved settings (preview).
     *
     * @param string $interval
     * @param string $cron_time
     * @param bool   $enabled
     * @return int Unix timestamp or 0.
     */
    public static function preview_next_timestamp($interval, $cron_time, $enabled)
    {
        if (! $enabled) {
            return 0;
        }

        $interval = is_string($interval) ? $interval : 'hourly';
        $time = self::parse_cron_time($cron_time);
        $now = time();

        if (in_array($interval, array('daily', 'twicedaily'), true)) {
            return self::next_timestamp_for_clock_time($time['hour'], $time['minute'], $now);
        }

        return $now + MINUTE_IN_SECONDS;
    }

    /**
     * @param int $hour
     * @param int $minute
     * @return string e.g. 3:05 AM (uses WordPress time format).
     */
    public static function format_clock_time($hour, $minute)
    {
        $tz = wp_timezone();
        $dt = (new DateTimeImmutable('now', $tz))->setTime($hour, $minute, 0);

        return wp_date(get_option('time_format'), $dt->getTimestamp());
    }

    /**
     * @param AutoDocs_Settings|null $settings
     * @param bool                   $prefer_soon Schedule first run ~1 minute from now (settings save / first enable).
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
        $first = self::compute_first_run_timestamp($settings, $prefer_soon);
        wp_schedule_event($first, $schedule, AutoDocs_Plugin::CRON_HOOK);
    }

    /**
     * Run overdue sync on any site request (not only the settings screen).
     *
     * @return bool True if a due run was executed.
     */
    public static function maybe_run_due_on_request($sync_service, $google_client, $settings)
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

        $hook = AutoDocs_Plugin::CRON_HOOK;
        $timestamp = wp_next_scheduled($hook);
        if (! $timestamp || $timestamp > time()) {
            return false;
        }

        return self::run_due_sync($sync_service, $google_client, $settings);
    }

    /**
     * Run sync when the scheduled time has passed, then queue the next full interval.
     *
     * @return bool True if a due run was executed.
     */
    public static function run_due_sync($sync_service, $google_client, $settings)
    {
        if ($settings->get('cron_enabled', '') !== '1') {
            return false;
        }

        if (get_transient(self::LOCK_TRANSIENT)) {
            return false;
        }

        $hook = AutoDocs_Plugin::CRON_HOOK;
        $timestamp = wp_next_scheduled($hook);
        if (! $timestamp || $timestamp > time()) {
            return false;
        }

        wp_clear_scheduled_hook($hook);
        self::run_scheduled_sync($sync_service, $google_client, $settings);
        self::reschedule($settings, false);

        return true;
    }

    /**
     * @return array{next_run_ts: int, last_run: string, last_run_ts: int, ran_now: bool, wp_cron_disabled: bool}
     */
    public static function status_payload($sync_service, $google_client, $settings)
    {
        if (! defined('DISABLE_WP_CRON') || ! DISABLE_WP_CRON) {
            spawn_cron();
        }

        $ran_now = self::run_due_sync($sync_service, $google_client, $settings);

        $last_raw = (string) get_option(AutoDocs_Sync_Meta::OPTION_LAST_CRON_RUN, '');
        $last_ts = 0;
        if ($last_raw !== '') {
            $dt = date_create_from_format('Y-m-d H:i:s', $last_raw, wp_timezone());
            if ($dt) {
                $last_ts = $dt->getTimestamp();
            }
        }

        return array(
            'next_run_ts' => self::next_run_timestamp(),
            'last_run' => self::last_run_formatted(),
            'last_run_ts' => $last_ts,
            'ran_now' => $ran_now,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        );
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
        $time_fmt = self::format_clock_time($time['hour'], $time['minute']);
        $tz_label = self::timezone_label();

        $parts = array();

        if ($interval === 'daily') {
            $parts[] = sprintf(
                /* translators: 1: time HH:MM, 2: timezone */
                __('Runs once per day at %1$s (%2$s).', 'autodocs-publisher'),
                $time_fmt,
                $tz_label
            );
        } elseif ($interval === 'twicedaily') {
            $second_h = ($time['hour'] + 12) % 24;
            $parts[] = sprintf(
                /* translators: 1: first time, 2: second time, 3: timezone */
                __('Runs twice daily at %1$s and %2$s (%3$s).', 'autodocs-publisher'),
                $time_fmt,
                self::format_clock_time($second_h, $time['minute']),
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

    /**
     * @return string
     */
    public static function next_run_formatted()
    {
        $ts = wp_next_scheduled(AutoDocs_Plugin::CRON_HOOK);
        if (! $ts) {
            return '';
        }

        return self::format_timestamp((int) $ts);
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

        $dt = date_create_from_format('Y-m-d H:i:s', $raw, wp_timezone());
        if (! $dt) {
            return '';
        }

        return self::format_timestamp($dt->getTimestamp());
    }
}
