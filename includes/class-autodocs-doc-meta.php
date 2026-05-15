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

        $prepared = self::prepare_html_for_meta($html);
        $plain = self::html_to_plain_text($prepared);

        if (! preg_match('/\[\\s*META\\s+START\\s*\](.*?)\[\\s*META\\s+END\\s*\]/is', $plain, $matches)) {
            return array('meta' => $meta, 'body_html' => $html);
        }

        $meta = self::parse_meta_lines($matches[1]);
        $body_html = self::remove_meta_block_from_html($html);

        return array('meta' => $meta, 'body_html' => $body_html);
    }

    /**
     * Insert line breaks before block boundaries so wp_strip_all_tags keeps one line per Doc paragraph.
     *
     * @param string $html
     * @return string
     */
    private static function prepare_html_for_meta($html)
    {
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr|td|th|blockquote)>/i', "$0\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        return is_string($html) ? $html : '';
    }

    /**
     * @param string $html
     * @return string
     */
    private static function html_to_plain_text($html)
    {
        $text = wp_strip_all_tags($html, true);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        return $text;
    }

    /**
     * @param string $block
     * @return array<string, string>
     */
    private static function parse_meta_lines($block)
    {
        $meta = array();

        foreach (preg_split("/\n+/", $block) as $line) {
            $line = trim($line);
            if ($line === '' || 0 === strpos($line, '#')) {
                continue;
            }
            $pos = strpos($line, ':');
            if (false === $pos) {
                continue;
            }
            $key = self::normalize_meta_key(trim(substr($line, 0, $pos)));
            $val = trim(substr($line, $pos + 1));
            if ($key !== '') {
                $meta[$key] = $val;
            }
        }

        return $meta;
    }

    /**
     * @param string $key
     * @return string
     */
    private static function normalize_meta_key($key)
    {
        $key = strtolower($key);

        $aliases = array(
            'category' => 'categories',
            'cats' => 'categories',
            'tag' => 'tags',
            'post-type' => 'post_type',
            'posttype' => 'post_type',
            'featured-image' => 'featured_image',
            'featuredimage' => 'featured_image',
        );

        return $aliases[$key] ?? $key;
    }

    /**
     * @param string $html
     * @return string
     */
    private static function remove_meta_block_from_html($html)
    {
        $out = preg_replace('/\[\\s*META\\s+START\\s*\].*?\[\\s*META\\s+END\\s*\]/is', '', $html, 1);

        return is_string($out) ? $out : $html;
    }
}
