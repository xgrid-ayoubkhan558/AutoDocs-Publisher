<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-doc-meta.php';

/**
 * Manual import / update from a Drive article folder (admin UI).
 */
final class AutoDocs_Sync_Import
{
    /** @var AutoDocs_Settings */
    private $settings;

    /** @var AutoDocs_Google_Client */
    private $google_client;

    /** @var AutoDocs_Sync_Repository */
    private $repository;

    /** @var AutoDocs_Sync_Media */
    private $media;

    public function __construct(
        AutoDocs_Settings $settings,
        AutoDocs_Google_Client $google_client,
        AutoDocs_Sync_Repository $repository,
        AutoDocs_Sync_Media $media
    ) {
        $this->settings = $settings;
        $this->google_client = $google_client;
        $this->repository = $repository;
        $this->media = $media;
    }

    /**
     * @return true|WP_Error
     */
    public function assert_folder_in_new_bucket($folder_id)
    {
        return $this->assert_folder_in_bucket_by_key($folder_id, 'new');
    }

    /**
     * @param string $folder_id
     * @param string $bucket_key new|synced (legacy "modified" is treated as synced)
     * @return true|WP_Error
     */
    public function assert_folder_in_bucket_by_key($folder_id, $bucket_key)
    {
        $folder_id = is_string($folder_id) ? trim($folder_id) : '';
        $bucket_key = sanitize_key((string) $bucket_key);
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        $map = array(
            'new' => 'folder_new',
            'synced' => 'folder_synced',
        );
        if (! isset($map[$bucket_key])) {
            return new WP_Error('autodocs_bad_bucket', __('Invalid bucket.', 'autodocs-publisher'));
        }

        $parent = (string) $this->settings->get($map[$bucket_key], '');
        if ($parent === '') {
            return new WP_Error(
                'autodocs_no_bucket',
                sprintf(
                    /* translators: %s: bucket label */
                    __('Set the %s bucket on the Drive & folders tab first.', 'autodocs-publisher'),
                    ucfirst($bucket_key)
                )
            );
        }

        $kids = $this->google_client->list_folders($parent);
        if (is_wp_error($kids)) {
            return $kids;
        }

        foreach ($kids as $row) {
            if (! empty($row['id']) && $row['id'] === $folder_id) {
                return true;
            }
        }

        return new WP_Error(
            'autodocs_folder_not_in_bucket',
            sprintf(
                /* translators: %s: bucket label */
                __('This folder is not inside your %s Drive bucket.', 'autodocs-publisher'),
                ucfirst($bucket_key)
            )
        );
    }

    /**
     * @return array|WP_Error
     */
    public function prepare_new_folder_import($folder_id)
    {
        return $this->prepare_folder_import($folder_id, 'new');
    }

    /**
     * @param string $folder_id
     * @param string $bucket_key new|synced|modified
     * @return array|WP_Error
     */
    public function prepare_folder_import($folder_id, $bucket_key = 'new')
    {
        $bucket_key = sanitize_key((string) $bucket_key);
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        if (! in_array($bucket_key, array('new', 'synced'), true)) {
            $bucket_key = 'new';
        }

        $check = $this->assert_folder_in_bucket_by_key($folder_id, $bucket_key);
        if (is_wp_error($check)) {
            return $check;
        }

        $files = $this->google_client->list_files_in_folder($folder_id);
        if (is_wp_error($files)) {
            return $files;
        }

        $doc_file = null;
        $image_file = null;
        $drive_files = array();
        foreach ($files as $file) {
            if (AutoDocs_Sync_Meta::MIME_DOC === ($file['mimeType'] ?? '') && null === $doc_file) {
                $doc_file = $file;
                continue;
            }
            $mt = $file['mimeType'] ?? '';
            $is_image = in_array(
                $mt,
                array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                true
            );
            $fname = isset($file['name']) ? (string) $file['name'] : '';
            $is_named = $fname !== '' && 0 === stripos($fname, 'featured');
            if ($is_image || $is_named) {
                if (null === $image_file) {
                    $image_file = $file;
                }
            }
            $drive_files[] = array(
                'id' => isset($file['id']) ? (string) $file['id'] : '',
                'name' => $fname !== '' ? $fname : __('(unnamed)', 'autodocs-publisher'),
                'mimeType' => $mt,
                'size' => isset($file['size']) ? (int) $file['size'] : 0,
                'web_view_link' => isset($file['webViewLink']) ? (string) $file['webViewLink'] : '',
                'thumbnail_url' => isset($file['thumbnailLink']) ? (string) $file['thumbnailLink'] : '',
            );
        }

        if (null === $doc_file) {
            return new WP_Error('autodocs_no_doc', __('No Google Doc found in this folder.', 'autodocs-publisher'));
        }

        $html = $this->google_client->export_doc_html($doc_file['id']);
        if (is_wp_error($html)) {
            return $html;
        }

        $parsed = AutoDocs_Doc_Meta::extract_from_export($this->google_client, $doc_file['id'], $html);
        $meta = $parsed['meta'];
        $body_html = $parsed['body_html'];

        $fmeta = $this->google_client->get_file_meta($folder_id);
        $folder_name = (! is_wp_error($fmeta) && ! empty($fmeta['name'])) ? (string) $fmeta['name'] : '';

        $title = isset($meta['title']) && $meta['title'] !== '' ? $meta['title'] : $folder_name;
        $slug = isset($meta['slug']) ? sanitize_title($meta['slug']) : '';
        if ($slug === '' && $title !== '') {
            $slug = sanitize_title($title);
        }

        $ptype = isset($meta['post_type']) ? sanitize_key($meta['post_type']) : 'post';
        if (! post_type_exists($ptype)) {
            $ptype = 'post';
        }

        $pstatus = isset($meta['status']) ? sanitize_key($meta['status']) : 'draft';
        if (! in_array($pstatus, array('publish', 'draft', 'pending', 'private', 'future'), true)) {
            $pstatus = 'draft';
        }

        $excerpt = isset($meta['excerpt']) ? (string) $meta['excerpt'] : '';

        $default_cat_ids = array();
        if (! empty($meta['categories'])) {
            $default_cat_ids = $this->create_or_get_category_ids_from_string($meta['categories']);
        }

        $doc_categories_preview = $this->split_meta_label_list(isset($meta['categories']) ? (string) $meta['categories'] : '');
        $doc_tags_preview = $this->split_meta_label_list(isset($meta['tags']) ? (string) $meta['tags'] : '');
        $doc_categories_taxonomy = $this->build_doc_taxonomy_preview($doc_categories_preview, 'category');
        $doc_tags_taxonomy = $this->build_doc_taxonomy_preview($doc_tags_preview, 'post_tag');

        $default_tags = '';
        if (! empty($meta['tags'])) {
            $default_tags = implode(', ', $this->split_meta_label_list($meta['tags']));
        }

        $types = array();
        foreach (get_post_types(array('public' => true), 'objects') as $obj) {
            $types[] = array('name' => $obj->name, 'label' => $obj->label);
        }

        $cats = array();
        $terms = get_terms(array('taxonomy' => 'category', 'hide_empty' => false, 'number' => 200));
        if (! is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $term) {
                if (isset($term->term_id)) {
                    $cats[] = array('id' => (int) $term->term_id, 'name' => $term->name);
                }
            }
        }

        $existing_id = $this->repository->post_id_for_folder($folder_id);

        $featured_preview = null;
        if ($image_file) {
            $featured_preview = array(
                'file_id' => $image_file['id'] ?? '',
                'name' => isset($image_file['name']) ? (string) $image_file['name'] : '',
                'thumbnail_url' => isset($image_file['thumbnailLink']) ? (string) $image_file['thumbnailLink'] : '',
            );
        }

        $sanitized_preview_html = $this->media->sanitize_google_html($body_html);
        $plain_for_stats = wp_strip_all_tags($sanitized_preview_html, true);
        $doc_stats = array(
            'type' => __('Google Doc', 'autodocs-publisher'),
            'modified' => isset($doc_file['modifiedTime']) ? (string) $doc_file['modifiedTime'] : '',
            'modified_formatted' => isset($doc_file['modifiedTime']) ? mysql2date(
                get_option('date_format') . ' ' . get_option('time_format'),
                $doc_file['modifiedTime']
            ) : '',
            'size' => isset($doc_file['size']) ? (int) $doc_file['size'] : 0,
            'word_count' => $plain_for_stats !== '' ? str_word_count($plain_for_stats) : 0,
            'image_count' => count(array_filter($drive_files, function ($f) {
                return in_array($f['mimeType'], array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true);
            })),
        );

        $cat_mode_default = ! empty($meta['categories']) ? 'doc' : 'manual';
        $tag_mode_default = ! empty($meta['tags']) ? 'doc' : 'manual';

        $acf_choices = AutoDocs_Acf_Helpers::list_body_target_fields($ptype);
        $acf_vals = AutoDocs_Acf_Helpers::choice_values($acf_choices);
        $def_acf = AutoDocs_Acf_Helpers::resolve_body_field_for_import($ptype, $acf_choices);
        $def_acf_sel = $def_acf['acf_body_field'];
        $def_acf_custom = $def_acf['acf_body_field_custom'];
        if ($def_acf_sel === '' && $def_acf_custom === '') {
            $site_acf = trim((string) $this->settings->get('acf_body_field', ''));
            if ($site_acf !== '' && in_array($site_acf, $acf_vals, true)) {
                $def_acf_sel = $site_acf;
            } elseif ($site_acf !== '') {
                $def_acf_sel = AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE;
                $def_acf_custom = $site_acf;
            }
        }

        return array(
            'folder_id' => $folder_id,
            'bucket_key' => $bucket_key,
            'folder_name' => $folder_name,
            'doc_file_id' => $doc_file['id'],
            'doc_modified' => isset($doc_file['modifiedTime']) ? $doc_file['modifiedTime'] : '',
            'meta_block' => $meta,
            'meta_keys' => array_keys($meta),
            'defaults' => array(
                'post_title' => $title,
                'post_name' => $slug,
                'post_type' => $ptype,
                'post_status' => $pstatus,
                'post_excerpt' => $excerpt,
                'categories' => $default_cat_ids,
                'tags' => $default_tags,
                'categories_mode' => $cat_mode_default,
                'tags_mode' => $tag_mode_default,
                'featured_image' => isset($meta['featured_image']) ? (string) $meta['featured_image'] : 'auto',
                'acf_body_field' => $def_acf_sel,
                'acf_body_field_custom' => $def_acf_custom,
            ),
            'body_preview' => $this->build_plain_body_preview($body_html),
            'content_html_preview' => $this->trim_html_preview($sanitized_preview_html),
            'has_meta_block' => ! empty($meta),
            'doc_stats' => $doc_stats,
            'drive_files' => $drive_files,
            'doc_categories_preview' => $doc_categories_preview,
            'doc_tags_preview' => $doc_tags_preview,
            'doc_categories_taxonomy' => $doc_categories_taxonomy,
            'doc_tags_taxonomy' => $doc_tags_taxonomy,
            'acf_body_field_choices' => $acf_choices,
            'acf_select_custom_value' => AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE,
            'acf_select_site_default_value' => AutoDocs_Acf_Helpers::SELECT_SITE_DEFAULT_VALUE,
            'post_types' => $types,
            'categories' => $cats,
            'existing_post_id' => $existing_id,
            'synced_bucket_set' => (string) $this->settings->get('folder_synced', '') !== '',
            'featured_preview' => $featured_preview,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{post_id: int, moved: bool}|WP_Error
     */
    public function import_folder_from_drive($folder_id, array $input)
    {
        $bucket_key = isset($input['bucket_key']) ? sanitize_key((string) $input['bucket_key']) : 'new';
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        if (! in_array($bucket_key, array('new', 'synced'), true)) {
            $bucket_key = 'new';
        }

        $check = $this->assert_folder_in_bucket_by_key($folder_id, $bucket_key);
        if (is_wp_error($check)) {
            return $check;
        }

        $files = $this->google_client->list_files_in_folder($folder_id);
        if (is_wp_error($files)) {
            return $files;
        }

        $doc_file = null;
        $image_file = null;
        foreach ($files as $file) {
            if (AutoDocs_Sync_Meta::MIME_DOC === $file['mimeType'] && null === $doc_file) {
                $doc_file = $file;
            }
            if (null === $image_file) {
                $is_image = in_array(
                    $file['mimeType'],
                    array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                    true
                );
                $fname = isset($file['name']) ? (string) $file['name'] : '';
                $is_named = $fname !== '' && 0 === stripos($fname, 'featured');
                if ($is_image || $is_named) {
                    $image_file = $file;
                }
            }
        }

        if (null === $doc_file) {
            return new WP_Error('autodocs_no_doc', __('No Google Doc found in this folder.', 'autodocs-publisher'));
        }

        $html = $this->google_client->export_doc_html($doc_file['id']);
        if (is_wp_error($html)) {
            return $html;
        }

        $parsed = AutoDocs_Doc_Meta::extract_from_export($this->google_client, $doc_file['id'], $html);
        $meta = $parsed['meta'];
        $body_html = $parsed['body_html'];

        $fmeta = $this->google_client->get_file_meta($folder_id);
        $folder_name = (! is_wp_error($fmeta) && ! empty($fmeta['name'])) ? (string) $fmeta['name'] : '';

        $title = ! empty($input['post_title']) ? sanitize_text_field(wp_unslash($input['post_title'])) : (isset($meta['title']) && $meta['title'] !== '' ? $meta['title'] : $folder_name);
        $slug_in = ! empty($input['post_name']) ? sanitize_title(wp_unslash($input['post_name'])) : '';
        if ($slug_in === '') {
            $slug_in = isset($meta['slug']) && $meta['slug'] !== '' ? sanitize_title($meta['slug']) : '';
        }
        if ($slug_in === '' && $title !== '') {
            $slug_in = sanitize_title($title);
        }

        $ptype = ! empty($input['post_type']) ? sanitize_key(wp_unslash($input['post_type'])) : (isset($meta['post_type']) ? sanitize_key($meta['post_type']) : 'post');
        if (! post_type_exists($ptype)) {
            $ptype = 'post';
        }

        $pstatus = ! empty($input['post_status']) ? sanitize_key(wp_unslash($input['post_status'])) : (isset($meta['status']) ? sanitize_key($meta['status']) : 'draft');
        if (! in_array($pstatus, array('publish', 'draft', 'pending', 'private', 'future'), true)) {
            $pstatus = 'draft';
        }

        $use_doc_excerpt = ! isset($input['use_doc_excerpt']) || ! empty($input['use_doc_excerpt']);
        if ($use_doc_excerpt) {
            $excerpt = isset($input['post_excerpt']) && (string) $input['post_excerpt'] !== ''
                ? sanitize_textarea_field(wp_unslash($input['post_excerpt']))
                : (isset($meta['excerpt']) ? (string) $meta['excerpt'] : '');
        } else {
            $excerpt = isset($input['post_excerpt']) ? sanitize_textarea_field(wp_unslash($input['post_excerpt'])) : '';
        }

        $bucket_opt_map = array(
            'new' => 'folder_new',
            'synced' => 'folder_synced',
        );
        $source_bucket_id = (string) $this->settings->get($bucket_opt_map[$bucket_key], '');
        $synced_bucket = (string) $this->settings->get('folder_synced', '');

        $sanitized_body = $this->media->sanitize_google_html($body_html);
        $acf_field = $this->resolve_acf_body_field_for_import($input);
        $use_acf = ($acf_field !== '' && function_exists('update_field'));
        $postarr = array(
            'post_title' => $title,
            'post_name' => $slug_in,
            'post_content' => $use_acf ? ' ' : $sanitized_body,
            'post_excerpt' => $excerpt,
            'post_status' => $pstatus,
            'post_type' => $ptype,
        );

        if (! empty($input['post_author'])) {
            $author_id = (int) $input['post_author'];
            if ($author_id > 0 && get_userdata($author_id)) {
                $postarr['post_author'] = $author_id;
            }
        }

        $existing = $this->repository->post_id_for_folder($folder_id);
        if ($existing) {
            $postarr['ID'] = $existing;
            $saved_id = wp_update_post(wp_slash($postarr), true);
        } else {
            $saved_id = wp_insert_post(wp_slash($postarr), true);
        }

        if (is_wp_error($saved_id)) {
            return $saved_id;
        }

        $post_id = (int) $saved_id;
        if ($use_acf) {
            update_field($acf_field, $sanitized_body, $post_id);
        }
        $postarr['post_name'] = wp_unique_post_slug($slug_in, $post_id, $pstatus, $ptype, 0);
        if ($postarr['post_name'] !== get_post_field('post_name', $post_id)) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_name' => $postarr['post_name'],
            ));
        }

        $cat_mode = isset($input['categories_mode']) && 'manual' === sanitize_key((string) $input['categories_mode']) ? 'manual' : 'doc';
        $cat_ids = array();
        if ('doc' === $cat_mode) {
            if (! empty($meta['categories'])) {
                $cat_ids = $this->create_or_get_category_ids_from_string($meta['categories']);
            }
        } elseif (! empty($input['categories']) && is_array($input['categories'])) {
            foreach ($input['categories'] as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $cat_ids[] = $cid;
                }
            }
        }
        $cat_ids = array_values(array_unique(array_filter($cat_ids)));
        if (! empty($cat_ids)) {
            wp_set_object_terms($post_id, $cat_ids, 'category', false);
        }

        $tag_mode = isset($input['tags_mode']) && 'manual' === sanitize_key((string) $input['tags_mode']) ? 'manual' : 'doc';
        $tags_str = '';
        if ('doc' === $tag_mode) {
            if (! empty($meta['tags'])) {
                $tags_str = implode(', ', $this->split_meta_label_list($meta['tags']));
            }
        } else {
            $tags_str = isset($input['tags']) ? sanitize_text_field(wp_unslash($input['tags'])) : '';
        }
        if ($tags_str !== '') {
            wp_set_post_tags($post_id, $tags_str, false);
        }

        update_post_meta($post_id, AutoDocs_Sync_Meta::META_FILE_ID, $doc_file['id']);
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_FOLDER_ID, $folder_id);
        if ($folder_name !== '') {
            update_post_meta($post_id, AutoDocs_Sync_Meta::META_FOLDER_NAME, sanitize_text_field($folder_name));
        }
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_MODIFIED, $doc_file['modifiedTime']);
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, 'synced');
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_LAST_SYNCED, current_time('mysql'));
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_LAST_SYNC_SOURCE, AutoDocs_Sync_Meta::SYNC_SOURCE_IMPORT);
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_CONTENT_HASH, hash('sha256', $body_html));
        update_post_meta($post_id, AutoDocs_Sync_Meta::META_SOURCE_ROOT, $source_bucket_id);
        if (! get_post_meta($post_id, AutoDocs_Sync_Meta::META_FIRST_IMPORTED, true)) {
            update_post_meta($post_id, AutoDocs_Sync_Meta::META_FIRST_IMPORTED, current_time('mysql'));
        }

        do_action('autodocs_map_doc_meta_to_post', $post_id, $meta, $body_html, $folder_id);

        $feat = isset($meta['featured_image']) ? strtolower(trim((string) $meta['featured_image'])) : 'auto';
        if ($feat === 'auto' || $feat === '') {
            if ($image_file) {
                $this->media->set_featured_image_from_drive($post_id, $image_file, $body_html);
            } else {
                $this->media->maybe_set_featured_image_from_html($post_id, $body_html);
            }
        }

        $moved = false;
        $do_move = ('new' === $bucket_key) && ! empty($input['move_to_synced']) && $synced_bucket !== '';
        if ($do_move) {
            $mv = $this->google_client->move_file_to_parent($folder_id, $synced_bucket);
            if (is_wp_error($mv)) {
                return $mv;
            }
            $moved = true;
            update_post_meta($post_id, AutoDocs_Sync_Meta::META_SOURCE_ROOT, $synced_bucket);
        }

        return array('post_id' => $post_id, 'moved' => $moved);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{post_id: int, moved: bool}|WP_Error
     */
    public function import_new_folder_from_drive($folder_id, array $input)
    {
        return $this->import_folder_from_drive($folder_id, $input);
    }

    /**
     * @param array<string, mixed> $input
     * @return string
     */
    private function resolve_acf_body_field_for_import(array $input)
    {
        if (! array_key_exists('acf_body_field', $input)) {
            return $this->get_acf_body_field_name();
        }
        $sel = isset($input['acf_body_field']) ? sanitize_text_field((string) $input['acf_body_field']) : '';
        if ($sel === AutoDocs_Acf_Helpers::SELECT_SITE_DEFAULT_VALUE) {
            return $this->get_acf_body_field_name();
        }
        if ($sel === AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE) {
            $custom = isset($input['acf_body_field_custom'])
                ? sanitize_text_field((string) $input['acf_body_field_custom'])
                : '';
            if ($custom !== '') {
                return $custom;
            }
            $sel = '';
        }
        if ($sel !== '') {
            return $sel;
        }

        $pt = isset($input['post_type']) ? sanitize_key((string) $input['post_type']) : 'post';
        if ($pt === '' || ! post_type_exists($pt)) {
            $pt = 'post';
        }
        $choices = AutoDocs_Acf_Helpers::list_body_target_fields($pt);
        $def = AutoDocs_Acf_Helpers::resolve_body_field_for_import($pt, $choices);

        return AutoDocs_Acf_Helpers::body_field_name_from_selection($def);
    }

    /**
     * @return string
     */
    private function get_acf_body_field_name()
    {
        return trim((string) $this->settings->get('acf_body_field', ''));
    }

    /**
     * @return int[]
     */
    private function create_or_get_category_ids_from_string($csv)
    {
        $ids = array();
        foreach ($this->split_meta_label_list($csv) as $name) {
            if ($name === '') {
                continue;
            }
            $t = term_exists($name, 'category');
            if (! $t) {
                $t = wp_insert_term($name, 'category');
            }
            if (is_wp_error($t)) {
                continue;
            }
            $ids[] = is_array($t) ? (int) $t['term_id'] : (int) $t;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param string[] $labels
     * @param string   $taxonomy category|post_tag
     * @return array<int, array{label: string, exists: bool}>
     */
    private function build_doc_taxonomy_preview(array $labels, $taxonomy)
    {
        $taxonomy = $taxonomy === 'post_tag' ? 'post_tag' : 'category';
        $items = array();
        foreach ($labels as $label) {
            $label = is_string($label) ? trim($label) : '';
            if ($label === '') {
                continue;
            }
            $exists = false;
            $t = term_exists($label, $taxonomy);
            if ($t) {
                $exists = true;
            } elseif ($taxonomy === 'post_tag') {
                $slug = sanitize_title($label);
                if ($slug !== '' && get_term_by('slug', $slug, 'post_tag')) {
                    $exists = true;
                }
            }
            $items[] = array(
                'label' => $label,
                'exists' => $exists,
            );
        }

        return $items;
    }

    /**
     * Categories / tags in doc meta may use commas, newlines, or "A - B - C" lists.
     *
     * @return string[]
     */
    private function split_meta_label_list($raw)
    {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return array();
        }

        $candidates = array();
        foreach (preg_split('/\r\n|\n|\r/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, ',') !== false) {
                foreach (array_map('trim', explode(',', $line)) as $chunk) {
                    if ($chunk !== '') {
                        $candidates[] = $chunk;
                    }
                }
                continue;
            }
            if (preg_match('/\s+-\s+/', $line)) {
                foreach (preg_split('/\s+-\s+/', $line) as $chunk) {
                    $chunk = trim($chunk);
                    if ($chunk !== '') {
                        $candidates[] = $chunk;
                    }
                }
                continue;
            }
            $candidates[] = $line;
        }

        $out = array();
        foreach ($candidates as $p) {
            $p = trim($p);
            $p = preg_replace('/^[\-\*\x{2022}]\s+/u', '', $p);
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param string $body_html
     * @return string
     */
    private function build_plain_body_preview($body_html)
    {
        $body_html = is_string($body_html) ? $body_html : '';
        $text = wp_strip_all_tags($body_html, true);
        if ($text === '') {
            return '';
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return $text !== '' ? wp_trim_words($text, 80, '…') : '';
    }

    /**
     * @param string $html
     * @return string
     */
    private function trim_html_preview($html)
    {
        $html = is_string($html) ? trim($html) : '';
        if ($html === '') {
            return '';
        }

        $html = wp_kses_post($html);
        if (strlen($html) > 120000) {
            $html = substr($html, 0, 120000) . '…';
        }

        return $html;
    }

    /**
     * @param string[] $folder_ids
     * @param string   $bucket_key
     * @param array<string, mixed> $input
     * @return array{imported: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public function import_folders_bulk(array $folder_ids, $bucket_key, array $input)
    {
        $bucket_key = sanitize_key((string) $bucket_key);
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        if (! in_array($bucket_key, array('new', 'synced'), true)) {
            $bucket_key = 'new';
        }

        $input['bucket_key'] = $bucket_key;

        $results = array();
        $imported = 0;
        $failed = 0;

        foreach ($folder_ids as $folder_id) {
            $folder_id = is_string($folder_id) ? trim($folder_id) : '';
            if ($folder_id === '') {
                continue;
            }

            $row_input = $input;
            $row_input['post_title'] = '';
            $row_input['post_name'] = '';
            $row_input['post_excerpt'] = '';

            $result = $this->import_folder_from_drive($folder_id, $row_input);
            if (is_wp_error($result)) {
                ++$failed;
                $results[] = array(
                    'folder_id' => $folder_id,
                    'success' => false,
                    'message' => $result->get_error_message(),
                );
                continue;
            }

            ++$imported;
            $edit = get_edit_post_link($result['post_id'], 'raw');
            $results[] = array(
                'folder_id' => $folder_id,
                'success' => true,
                'post_id' => (int) $result['post_id'],
                'moved' => ! empty($result['moved']),
                'edit_url' => $edit ? $edit : '',
            );
        }

        return array(
            'imported' => $imported,
            'failed' => $failed,
            'results' => $results,
        );
    }

    /**
     * Import article folders in the New bucket that are not linked to a post yet (automatic sync).
     *
     * @param int $max_per_run Maximum folders to import per cron run.
     * @return array{imported: int, failed: int, skipped: int}
     */
    public function import_new_bucket_folders_for_cron($max_per_run = 10)
    {
        $max_per_run = max(1, min(25, (int) $max_per_run));
        $new_bucket_id = (string) $this->settings->get('folder_new', '');
        if ($new_bucket_id === '') {
            return array('imported' => 0, 'failed' => 0, 'skipped' => 0);
        }

        $folders = $this->google_client->list_folders($new_bucket_id);
        if (is_wp_error($folders)) {
            return array('imported' => 0, 'failed' => 0, 'skipped' => 0);
        }

        $folder_ids = array();
        foreach ($folders as $folder) {
            $folder_id = isset($folder['id']) ? (string) $folder['id'] : '';
            if ($folder_id === '' || $this->repository->post_id_for_folder($folder_id)) {
                continue;
            }
            $folder_ids[] = $folder_id;
            if (count($folder_ids) >= $max_per_run) {
                break;
            }
        }

        if ($folder_ids === array()) {
            return array('imported' => 0, 'failed' => 0, 'skipped' => 0);
        }

        $author = (int) get_option('autodocs_cron_import_author', 0);
        if ($author <= 0 || ! get_userdata($author)) {
            $admins = get_users(
                array(
                    'role' => 'administrator',
                    'number' => 1,
                    'fields' => 'ID',
                )
            );
            $author = ! empty($admins) ? (int) $admins[0] : 1;
        }

        $input = array(
            'bucket_key' => 'new',
            'categories_mode' => 'doc',
            'tags_mode' => 'doc',
            'move_to_synced' => true,
            'use_doc_excerpt' => true,
            'post_author' => $author,
            'acf_body_field' => AutoDocs_Acf_Helpers::SELECT_SITE_DEFAULT_VALUE,
        );

        $bulk = $this->import_folders_bulk($folder_ids, 'new', $input);

        return array(
            'imported' => (int) ($bulk['imported'] ?? 0),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'skipped' => max(0, count($folders) - count($folder_ids)),
        );
    }
}
