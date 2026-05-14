<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * HTML sanitization and featured image handling for synced/imported posts.
 */
final class AutoDocs_Sync_Media
{
    /** @var AutoDocs_Google_Client */
    private $google_client;

    public function __construct(AutoDocs_Google_Client $google_client)
    {
        $this->google_client = $google_client;
    }

    public function sanitize_google_html($html)
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['img'] = array('src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true, 'class' => true);
        $allowed['style'] = array();

        return wp_kses($html, $allowed);
    }

    /**
     * Download an image file from Drive and set as featured image.
     *
     * @param int   $post_id
     * @param array $image_file Drive file row
     * @param mixed $html_fallback
     */
    public function set_featured_image_from_drive($post_id, array $image_file, $html_fallback)
    {
        if (has_post_thumbnail($post_id)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $bytes = $this->google_client->download_file($image_file['id']);
        if (is_wp_error($bytes)) {
            $this->maybe_set_featured_image_from_html($post_id, $html_fallback);

            return;
        }

        $tmp = wp_tempnam($image_file['name']);
        file_put_contents($tmp, $bytes); // phpcs:ignore

        $file_array = array(
            'name' => sanitize_file_name($image_file['name']),
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload($file_array, $post_id);
        @unlink($tmp); // phpcs:ignore

        if (! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    /**
     * @param int    $post_id
     * @param string $html
     */
    public function maybe_set_featured_image_from_html($post_id, $html)
    {
        if (has_post_thumbnail($post_id) || ! preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            return;
        }

        $image_url = esc_url_raw(html_entity_decode($matches[1]));
        if (! $image_url || 0 !== strpos($image_url, 'http')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');
        if (! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }
}
