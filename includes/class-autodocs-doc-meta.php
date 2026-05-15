<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses [META START] ... [META END] blocks from Google Docs (Docs API + HTML export).
 */
class AutoDocs_Doc_Meta
{
    const MARKER_START = '[META START]';
    const MARKER_END = '[META END]';

    /**
     * Preferred entry: Docs API plain text for meta, HTML export for body.
     *
     * @param AutoDocs_Google_Client $google_client
     * @param string               $doc_file_id
     * @param string               $html
     * @return array{meta: array<string, string>, body_html: string}
     */
    public static function extract_from_export(AutoDocs_Google_Client $google_client, $doc_file_id, $html)
    {
        $html = is_string($html) ? $html : '';
        $meta = array();
        $doc_file_id = is_string($doc_file_id) ? trim($doc_file_id) : '';

        if ($doc_file_id !== '') {
            $doc = $google_client->get_document($doc_file_id);
            if (! is_wp_error($doc) && is_array($doc)) {
                $plain = self::plain_text_from_docs_api($doc);
                $meta = self::parse_meta_from_plain($plain);
            }
        }

        if (! empty($meta)) {
            $body_html = $html !== '' ? self::remove_meta_block_from_html($html) : $html;

            return array('meta' => $meta, 'body_html' => $body_html);
        }

        return self::parse_and_strip($html);
    }

    /**
     * @param string $html
     * @return array{meta: array<string, string>, body_html: string}
     */
    public static function parse_and_strip($html)
    {
        $html = is_string($html) ? $html : '';
        $meta = array();

        $plain = self::html_to_plain_text(self::prepare_html_for_meta($html));

        if (! preg_match('/\[\\s*META\\s+START\\s*\](.*?)\[\\s*META\\s+END\\s*\]/is', $plain, $matches)) {
            return array('meta' => $meta, 'body_html' => $html);
        }

        $meta = self::parse_meta_lines($matches[1]);
        $body_html = self::remove_meta_block_from_html($html);

        return array('meta' => $meta, 'body_html' => $body_html);
    }

    /**
     * @param array<string, mixed> $doc
     * @return string
     */
    public static function plain_text_from_docs_api(array $doc)
    {
        $body = isset($doc['body']['content']) && is_array($doc['body']['content']) ? $doc['body']['content'] : array();

        return self::collect_docs_api_text($body);
    }

    /**
     * @param array<int, array<string, mixed>> $elements
     * @return string
     */
    private static function collect_docs_api_text(array $elements)
    {
        $out = '';

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            if (isset($element['paragraph']) && is_array($element['paragraph'])) {
                $els = isset($element['paragraph']['elements']) && is_array($element['paragraph']['elements'])
                    ? $element['paragraph']['elements']
                    : array();
                foreach ($els as $el) {
                    if (isset($el['textRun']['content'])) {
                        $out .= (string) $el['textRun']['content'];
                    }
                }
                continue;
            }

            if (isset($element['table']) && is_array($element['table'])) {
                $rows = isset($element['table']['tableRows']) && is_array($element['table']['tableRows'])
                    ? $element['table']['tableRows']
                    : array();
                foreach ($rows as $row) {
                    if (! isset($row['tableCells']) || ! is_array($row['tableCells'])) {
                        continue;
                    }
                    foreach ($row['tableCells'] as $cell) {
                        if (isset($cell['content']) && is_array($cell['content'])) {
                            $out .= self::collect_docs_api_text($cell['content']);
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param string $plain
     * @return array<string, string>
     */
    public static function parse_meta_from_plain($plain)
    {
        $plain = self::normalize_plain($plain);

        if (! preg_match('/\[\\s*META\\s+START\\s*\](.*?)\[\\s*META\\s+END\\s*\]/is', $plain, $matches)) {
            return array();
        }

        return self::parse_meta_lines($matches[1]);
    }

    /**
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
        if (class_exists('DOMDocument') && is_string($html) && $html !== '') {
            $prev = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html)) {
                $root = $dom->getElementsByTagName('body')->item(0);
                if (null === $root) {
                    $root = $dom->documentElement;
                }
                $text = self::dom_node_plain_text($root);
                libxml_clear_errors();
                libxml_use_internal_errors($prev);

                return self::normalize_plain($text);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }

        $text = wp_strip_all_tags($html, true);

        return self::normalize_plain($text);
    }

    /**
     * @param DOMNode|null $node
     * @return string
     */
    private static function dom_node_plain_text($node)
    {
        if (null === $node) {
            return '';
        }

        if (XML_TEXT_NODE === $node->nodeType) {
            return $node->textContent;
        }

        $tag = strtolower($node->nodeName);
        $block = in_array($tag, array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr', 'br'), true);
        $text = '';

        if ($node->childNodes) {
            foreach ($node->childNodes as $child) {
                $text .= self::dom_node_plain_text($child);
            }
        }

        return $block ? $text . "\n" : $text;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function normalize_plain($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $text = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}]/u', ' ', $text);

        $map = array(
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{FF1A}" => ':',
            "\u{FF3B}" => '[',
            "\u{FF3D}" => ']',
        );
        $text = strtr($text, $map);

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
            $parsed = self::parse_meta_line($line);
            if (null === $parsed) {
                continue;
            }
            $meta[$parsed[0]] = $parsed[1];
        }

        return $meta;
    }

    /**
     * @param string $line
     * @return array{0: string, 1: string}|null
     */
    private static function parse_meta_line($line)
    {
        $line = trim($line);
        if ($line === '' || '#' === $line[0]) {
            return null;
        }

        $known_keys = array(
            'title',
            'slug',
            'post_type',
            'status',
            'categories',
            'category',
            'tags',
            'tag',
            'featured_image',
            'excerpt',
        );

        foreach ($known_keys as $key) {
            if (preg_match('/^' . preg_quote($key, '/') . '\s*[:：]\s*(.*)$/iu', $line, $matches)) {
                return array(self::normalize_meta_key($key), trim($matches[1]));
            }
        }

        if (preg_match('/^([a-z][a-z0-9_-]*)\s*[:：]\s*(.*)$/i', $line, $matches)) {
            return array(self::normalize_meta_key($matches[1]), trim($matches[2]));
        }

        $pos = strpos($line, ':');
        if (false === $pos) {
            $pos = mb_strpos($line, '：');
        }
        if (false === $pos) {
            return null;
        }

        $key = self::normalize_meta_key(trim(substr($line, 0, $pos)));
        $val = trim(substr($line, $pos + 1));

        return $key !== '' ? array($key, $val) : null;
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
        $prepared = self::prepare_html_for_meta($html);
        $out = preg_replace('/\[\\s*META\\s+START\\s*\].*?\[\\s*META\\s+END\\s*\]/is', '', $prepared, 1);
        if (is_string($out) && $out !== $prepared) {
            return $out;
        }

        $out = preg_replace('/\[\\s*META\\s+START\\s*\].*?\[\\s*META\\s+END\\s*\]/is', '', $html, 1);

        return is_string($out) ? $out : $html;
    }
}
