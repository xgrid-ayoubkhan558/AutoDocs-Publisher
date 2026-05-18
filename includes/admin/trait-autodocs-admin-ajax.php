<?php

if (!defined('ABSPATH')) {
    exit;
}

trait AutoDocs_Admin_Ajax_Trait
{
    /**
     * Require manage_options, Google connection, and OAuth scope version.
     */
    private function guard_google_tokens_ok()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }
        if (! $this->google_client->is_connected()) {
            wp_send_json_error(array('message' => __('Connect Google Drive first.', 'autodocs-publisher')));
        }
        if ($this->google_client->tokens_need_reconnect_for_scopes()) {
            wp_send_json_error(array(
                'message' => __('Your Google connection must be renewed. Open the Settings tab, click Disconnect Google, then Connect again so Drive permissions (full Drive, Docs read-only, account email) are granted.', 'autodocs-publisher'),
                'code' => 'needs_reconnect',
            ));
        }
    }

    public function ajax_cron_preview()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $interval = isset($_POST['interval']) ? sanitize_key(wp_unslash($_POST['interval'])) : 'hourly';
        $cron_time = isset($_POST['cron_time']) ? sanitize_text_field(wp_unslash($_POST['cron_time'])) : '03:00';
        $enabled = ! empty($_POST['enabled']);

        $ts = AutoDocs_Cron::preview_next_timestamp($interval, $cron_time, $enabled);

        wp_send_json_success(
            array(
                'next_run' => $ts > 0 ? AutoDocs_Cron::format_timestamp($ts) : '',
                'next_run_ts' => $ts,
                'next_run_relative' => $ts > 0 ? AutoDocs_Cron::relative_until_formatted($ts) : '',
                'repeat_label' => $enabled ? AutoDocs_Cron::interval_repeat_label($interval) : '',
                'site_now' => AutoDocs_Cron::site_now_formatted(),
            )
        );
    }

    public function ajax_sync_now()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $this->sync_service->refresh_known_statuses();
        $result = $this->sync_service->sync_all(true, AutoDocs_Sync_Meta::SYNC_SOURCE_MANUAL);
        wp_send_json_success($result);
    }

    public function ajax_refresh_statuses()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $this->sync_service->refresh_known_statuses();
        wp_send_json_success(array('message' => __('Statuses refreshed.', 'autodocs-publisher')));
    }

    public function ajax_list_drive_folders()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');

        $this->guard_google_tokens_ok();

        $parent_id = isset($_POST['parent_id']) ? sanitize_text_field(wp_unslash($_POST['parent_id'])) : 'root';
        if ('' === $parent_id) {
            $parent_id = 'root';
        }

        if ('root' !== $parent_id && (strlen($parent_id) > 256 || ! preg_match('/^[A-Za-z0-9._-]+$/', $parent_id))) {
            wp_send_json_error(array('message' => __('Invalid folder id.', 'autodocs-publisher')));
        }

        $folders = $this->google_client->list_folders($parent_id);
        if (is_wp_error($folders)) {
            wp_send_json_error(array('message' => $folders->get_error_message()));
        }

        wp_send_json_success(array(
            'folders' => $folders,
            'parent_id' => $parent_id,
        ));
    }

    public function ajax_prepare_import_new()
    {
        check_ajax_referer('autodocs_import', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $this->guard_google_tokens_ok();

        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field(wp_unslash($_POST['folder_id'])) : '';
        if ($folder_id === '' || strlen($folder_id) > 256 || ! preg_match('/^[A-Za-z0-9._-]+$/', $folder_id)) {
            wp_send_json_error(array('message' => __('Invalid folder id.', 'autodocs-publisher')));
        }

        $bucket_key = isset($_POST['bucket_key']) ? sanitize_key((string) wp_unslash($_POST['bucket_key'])) : 'new';
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        if (! in_array($bucket_key, array('new', 'synced'), true)) {
            $bucket_key = 'new';
        }

        $data = $this->sync_service->prepare_folder_import($folder_id, $bucket_key);
        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
        }

        wp_send_json_success($data);
    }

    /**
     * ACF body-target fields for the import UI, scoped to a post type (matches ACF location rules).
     */
    public function ajax_import_acf_body_choices()
    {
        check_ajax_referer('autodocs_import', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $pt = isset($_POST['post_type']) ? sanitize_key((string) wp_unslash($_POST['post_type'])) : 'post';
        if (! post_type_exists($pt)) {
            $pt = 'post';
        }

        $choices = AutoDocs_Acf_Helpers::list_body_target_fields($pt);
        $def_acf = AutoDocs_Acf_Helpers::resolve_body_field_for_import($pt, $choices);

        wp_send_json_success(array(
            'acf_body_field_choices' => $choices,
            'acf_select_custom_value' => AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE,
            'default_acf_body_field' => $def_acf['acf_body_field'],
            'default_acf_body_field_custom' => $def_acf['acf_body_field_custom'],
        ));
    }

    public function ajax_import_new_folder()
    {
        check_ajax_referer('autodocs_import', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $this->guard_google_tokens_ok();

        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field(wp_unslash($_POST['folder_id'])) : '';
        if ($folder_id === '' || strlen($folder_id) > 256 || ! preg_match('/^[A-Za-z0-9._-]+$/', $folder_id)) {
            wp_send_json_error(array('message' => __('Invalid folder id.', 'autodocs-publisher')));
        }

        $bucket_key = isset($_POST['bucket_key']) ? sanitize_key((string) wp_unslash($_POST['bucket_key'])) : 'new';
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        if (! in_array($bucket_key, array('new', 'synced'), true)) {
            $bucket_key = 'new';
        }

        $cats = array();
        if (! empty($_POST['categories']) && is_array($_POST['categories'])) {
            foreach ($_POST['categories'] as $cid) {
                $cats[] = (int) $cid;
            }
        }

        $input = array(
            'bucket_key' => $bucket_key,
            'post_title' => isset($_POST['post_title']) ? wp_unslash($_POST['post_title']) : '',
            'post_name' => isset($_POST['post_name']) ? wp_unslash($_POST['post_name']) : '',
            'post_type' => isset($_POST['post_type']) ? wp_unslash($_POST['post_type']) : '',
            'post_status' => isset($_POST['post_status']) ? wp_unslash($_POST['post_status']) : '',
            'post_excerpt' => isset($_POST['post_excerpt']) ? wp_unslash($_POST['post_excerpt']) : '',
            'tags' => isset($_POST['tags']) ? wp_unslash($_POST['tags']) : '',
            'categories' => $cats,
            'categories_mode' => isset($_POST['categories_mode']) ? wp_unslash($_POST['categories_mode']) : '',
            'tags_mode' => isset($_POST['tags_mode']) ? wp_unslash($_POST['tags_mode']) : '',
            'move_to_synced' => ! empty($_POST['move_to_synced']),
            'acf_body_field' => isset($_POST['acf_body_field']) ? sanitize_text_field(wp_unslash($_POST['acf_body_field'])) : '',
            'acf_body_field_custom' => isset($_POST['acf_body_field_custom']) ? sanitize_text_field(wp_unslash($_POST['acf_body_field_custom'])) : '',
        );

        $result = $this->sync_service->import_new_folder_from_drive($folder_id, $input);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $edit = get_edit_post_link($result['post_id'], 'raw');
        wp_send_json_success(array(
            'post_id' => $result['post_id'],
            'moved' => ! empty($result['moved']),
            'edit_url' => $edit ? $edit : '',
        ));
    }

    public function ajax_bulk_import_folders()
    {
        check_ajax_referer('autodocs_import', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        $this->guard_google_tokens_ok();

        $folder_ids_raw = isset($_POST['folder_ids']) ? wp_unslash($_POST['folder_ids']) : '';
        $folder_ids = is_string($folder_ids_raw) ? json_decode($folder_ids_raw, true) : array();
        if (! is_array($folder_ids)) {
            $folder_ids = array();
        }
        $folder_ids = array_values(array_filter(array_map('strval', $folder_ids)));

        if (empty($folder_ids)) {
            wp_send_json_error(array('message' => __('No articles selected.', 'autodocs-publisher')));
        }

        $bucket_key = isset($_POST['bucket_key']) ? sanitize_key((string) wp_unslash($_POST['bucket_key'])) : 'new';
        if ('modified' === $bucket_key) {
            $bucket_key = 'synced';
        }
        if (! in_array($bucket_key, array('new', 'synced'), true)) {
            $bucket_key = 'new';
        }

        $cats = array();
        if (! empty($_POST['categories']) && is_array($_POST['categories'])) {
            foreach ($_POST['categories'] as $cid) {
                $cats[] = (int) $cid;
            }
        }

        $input = array(
            'bucket_key' => $bucket_key,
            'post_type' => isset($_POST['post_type']) ? wp_unslash($_POST['post_type']) : '',
            'post_status' => isset($_POST['post_status']) ? wp_unslash($_POST['post_status']) : '',
            'post_author' => isset($_POST['post_author']) ? (int) $_POST['post_author'] : 0,
            'tags' => isset($_POST['tags']) ? wp_unslash($_POST['tags']) : '',
            'categories' => $cats,
            'categories_mode' => isset($_POST['categories_mode']) ? wp_unslash($_POST['categories_mode']) : '',
            'tags_mode' => isset($_POST['tags_mode']) ? wp_unslash($_POST['tags_mode']) : '',
            'move_to_synced' => ! empty($_POST['move_to_synced']),
            'use_doc_excerpt' => ! empty($_POST['use_doc_excerpt']),
            'acf_body_field' => isset($_POST['acf_body_field']) ? sanitize_text_field(wp_unslash($_POST['acf_body_field'])) : '',
            'acf_body_field_custom' => isset($_POST['acf_body_field_custom']) ? sanitize_text_field(wp_unslash($_POST['acf_body_field_custom'])) : '',
        );

        $result = $this->sync_service->import_folders_bulk($folder_ids, $bucket_key, $input);
        wp_send_json_success($result);
    }

    public function ajax_drive_item_meta()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');
        $this->guard_google_tokens_ok();

        $file_id = isset($_POST['file_id']) ? sanitize_text_field(wp_unslash($_POST['file_id'])) : '';
        if ($file_id === '' || strlen($file_id) > 256 || ! preg_match('/^[A-Za-z0-9._-]+$/', $file_id)) {
            wp_send_json_error(array('message' => __('Invalid file id.', 'autodocs-publisher')));
        }

        $meta = $this->google_client->get_file_meta($file_id);
        if (is_wp_error($meta)) {
            wp_send_json_error(array('message' => $meta->get_error_message()));
        }

        wp_send_json_success(array(
            'id' => isset($meta['id']) ? (string) $meta['id'] : $file_id,
            'name' => isset($meta['name']) ? (string) $meta['name'] : '',
        ));
    }

    public function ajax_list_bucket_articles()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');
        $this->guard_google_tokens_ok();

        $bucket_id = isset($_POST['bucket_id']) ? sanitize_text_field(wp_unslash($_POST['bucket_id'])) : '';
        $bucket_label = isset($_POST['bucket_label']) ? sanitize_text_field(wp_unslash($_POST['bucket_label'])) : '';
        $list_mode = isset($_POST['list_mode']) ? sanitize_key((string) wp_unslash($_POST['list_mode'])) : '';

        if ($bucket_id === '' || strlen($bucket_id) > 256 || ! preg_match('/^[A-Za-z0-9._-]+$/', $bucket_id)) {
            wp_send_json_error(array('message' => __('Invalid bucket id.', 'autodocs-publisher')));
        }

        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(50, (int) $_POST['per_page'])) : 20;

        if ('modified' === $list_mode) {
            $synced = (string) $this->settings->get('folder_synced', '');
            if ($synced === '' || $bucket_id !== $synced) {
                wp_send_json_error(array('message' => __('Choose a Synced bucket folder on the Drive tab to list modified articles.', 'autodocs-publisher')));
            }
            $result = $this->sync_service->list_modified_articles_paged($bucket_label, $page, $per_page);
        } else {
            $result = $this->sync_service->list_bucket_articles_paged($bucket_id, $bucket_label, $page, $per_page);
        }
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function ajax_sidebar_snapshot()
    {
        check_ajax_referer('autodocs_sync_now', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'autodocs-publisher')), 403);
        }

        if (! $this->google_client->is_connected()) {
            wp_send_json_success(array(
                'connected' => false,
                'needs_reconnect' => false,
            ));
        }

        if ($this->google_client->tokens_need_reconnect_for_scopes()) {
            wp_send_json_success(array(
                'connected' => true,
                'needs_reconnect' => true,
            ));
        }

        $settings = $this->settings->all();
        $fn = isset($settings['folder_new']) ? trim((string) $settings['folder_new']) : '';
        $fs = isset($settings['folder_synced']) ? trim((string) $settings['folder_synced']) : '';

        $email = $this->google_client->fetch_user_email();
        $email_str = is_wp_error($email) ? '' : $email;

        $payload = array(
            'connected' => true,
            'needs_reconnect' => false,
            'email' => $email_str,
            'last_sync_formatted' => $this->sync_service->last_site_sync_formatted(),
        );

        if (! empty($_POST['include_recent'])) {
            $payload['recent_syncs'] = $this->sync_service->list_recent_synced_posts(12);
        }

        $include_counts = ! empty($_POST['include_counts']);
        if ($include_counts) {
            $count_bucket = function ($bucket_id) {
                if ($bucket_id === '') {
                    return 0;
                }
                $r = $this->sync_service->count_bucket_articles($bucket_id);

                return is_wp_error($r) ? 0 : (int) $r;
            };

            $n = $count_bucket($fn);
            $s = $count_bucket($fs);
            $mod = $this->sync_service->count_modified_articles_in_synced_bucket();

            $payload['counts'] = array(
                'new' => $n,
                'synced' => $s,
                'modified' => $mod,
                'total' => $n + $s,
            );
        }

        wp_send_json_success($payload);
    }
}
