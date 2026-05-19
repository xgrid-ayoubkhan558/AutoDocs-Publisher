<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WordPress timezone display helpers for cron settings.
 */
final class AutoDocs_Cron_Timezone
{
    /**
     * @return string
     */
    public static function label()
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
    public static function for_intl()
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
    public static function gmt_offset_hours()
    {
        return (float) get_option('gmt_offset', 0);
    }
}
