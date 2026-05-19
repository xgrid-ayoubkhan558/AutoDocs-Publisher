<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WordPress site timezone formatting for sync/cron UI.
 */
final class AutoDocs_Sync_Datetime
{
    /**
     * @param string $mysql Datetime stored via current_time( 'mysql' ).
     * @return int Unix timestamp or 0.
     */
    public static function mysql_to_timestamp($mysql)
    {
        $mysql = trim((string) $mysql);
        if ($mysql === '') {
            return 0;
        }

        $dt = date_create_from_format('Y-m-d H:i:s', $mysql, wp_timezone());

        return $dt ? $dt->getTimestamp() : 0;
    }

    /**
     * @param int $timestamp Unix timestamp.
     * @return string
     */
    public static function format_site_datetime($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    /**
     * @param int $timestamp Unix timestamp.
     * @return string Date/time plus timezone label.
     */
    public static function format_with_timezone($timestamp)
    {
        $formatted = self::format_site_datetime($timestamp);
        if ($formatted === '') {
            return '';
        }

        return sprintf('%s (%s)', $formatted, AutoDocs_Cron_Timezone::label());
    }

    /**
     * @return string Current site time with timezone label.
     */
    public static function site_now_with_timezone()
    {
        return wp_date(get_option('time_format'), time()) . ' (' . AutoDocs_Cron_Timezone::label() . ')';
    }

    /**
     * @param int $timestamp Unix timestamp in the future or past.
     * @return string e.g. "in 5 minutes" or "2 hours ago"
     */
    public static function relative_to_now($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $now = time();
        if ($timestamp <= $now) {
            return sprintf(
                /* translators: %s: human time diff */
                __('%s ago', 'autodocs-publisher'),
                human_time_diff($timestamp, $now, true)
            );
        }

        return sprintf(
            /* translators: %s: human time diff */
            __('in %s', 'autodocs-publisher'),
            human_time_diff($now, $timestamp, true)
        );
    }
}
