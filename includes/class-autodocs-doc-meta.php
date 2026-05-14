<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses [META START] ... [META END] blocks from Google Doc HTML export.
 */
class AutoDocs_Doc_Meta
{
    const MARKER_START = '[META START]';
    const MARKER_END = '[META END]';

    /**
     * @param string $html
     * @return array{meta: array<string, string>, body_html: string}
     */
    public static function parse_and_strip($html)
    {
        $html = is_string($html) ? $html : '';
        $meta = array();

        $start = stripos($html, self::MARKER_START);
        $end = stripos($html, self::MARKER_END);

        if (false === $start || false === $end || $end <= $start) {
            return array('meta' => $meta, 'body_html' => $html);
        }

        $inner_start = $start + strlen(self::MARKER_START);
        $block = substr($html, $inner_start, $end - $inner_start);
        $plain = wp_strip_all_tags($block, true);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach (preg_split("/\r\n|\n|\r/", $plain) as $line) {
            $line = trim($line);
            if ($line === '' || 0 === strpos($line, '#')) {
                continue;
            }
            $pos = strpos($line, ':');
            if (false === $pos) {
                continue;
            }
            $key = strtolower(trim(substr($line, 0, $pos)));
            $val = trim(substr($line, $pos + 1));
            if ($key !== '') {
                $meta[$key] = $val;
            }
        }

        $before = substr($html, 0, $start);
        $after = substr($html, $end + strlen(self::MARKER_END));
        $body = $before . $after;

        return array('meta' => $meta, 'body_html' => $body);
    }
}
