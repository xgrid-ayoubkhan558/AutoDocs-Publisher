<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-doc-meta.php';

/**
 * Scheduled / bulk sync: Drive article folders → WordPress posts.
 */
final class AutoDocs_Sync_Engine
{
    /** @var AutoDocs_Settings */
    private $settings;

    /** @var AutoDocs_Google_Client */
    private $google_client;

    /** @var AutoDocs_Sync_Repository */
    private $repository;

    /** @var AutoDocs_Sync_Media */
    private $media;

    /** @var AutoDocs_Sync_Status_Manager */
    private $status;

    public function __construct(
        AutoDocs_Settings $settings,
        AutoDocs_Google_Client $google_client,
        AutoDocs_Sync_Repository $repository,
        AutoDocs_Sync_Media $media,
        AutoDocs_Sync_Status_Manager $status
    ) {
        $this->settings = $settings;
        $this->google_client = $google_client;
        $this->repository = $repository;
        $this->media = $media;
        $this->status = $status;
    }

    public function sync_all_configured_folders()
    {
        return $this->sync_all(false);
    }

    public function sync_all($force = false)
    {
        $results = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'missing' => 0,
            'errors' => array(),
        );

        $working_folder = $this->settings->get('working_folder_id', '');
        $bucket_ids = $this->status->status_bucket_folder_ids();

        if (! empty($bucket_ids)) {
            $folder_new_id = (string) $this->settings->get('folder_new', '');
            foreach ($bucket_ids as $bucket_id) {
                if ($folder_new_id !== '' && $bucket_id === $folder_new_id) {
                    continue;
                }
                $folder_result = $this->sync_working_folder($bucket_id, $force, false);
                foreach (array('created', 'updated', 'skipped', 'missing') as $key) {
                    $results[$key] += (int) ($folder_result[$key] ?? 0);
                }
                if (! empty($folder_result['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $folder_result['errors']);
                }
            }
        } elseif ($working_folder) {
            $folder_result = $this->sync_working_folder($working_folder, $force, true);
            foreach (array('created', 'updated', 'skipped', 'missing') as $key) {
                $results[$key] += (int) ($folder_result[$key] ?? 0);
            }
            if (! empty($folder_result['errors'])) {
                $results['errors'] = array_merge($results['errors'], $folder_result['errors']);
            }
        }

        return $results;
    }

    /**
     * @param string $root_folder_id
     * @param bool   $force
     * @param bool   $filter_status_subfolders
     * @return array{created: int, updated: int, skipped: int, missing: int, errors: string[]}
     */
    public function sync_working_folder($root_folder_id, $force = false, $filter_status_subfolders = true)
    {
        $results = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => array());

        $post_folders = $this->google_client->list_folders($root_folder_id);
        if (is_wp_error($post_folders)) {
            $results['errors'][] = $post_folders->get_error_message();

            return $results;
        }

        $status_folder_ids = array();
        if ($filter_status_subfolders) {
            $status_folder_ids = array_filter(array(
                $this->settings->get('folder_synced'),
                $this->settings->get('folder_new'),
                $this->settings->get('folder_missing'),
            ));
        }

        $seen_folder_ids = array();

        foreach ($post_folders as $post_folder) {
            if ($filter_status_subfolders && in_array($post_folder['id'], $status_folder_ids, true)) {
                continue;
            }

            $seen_folder_ids[] = $post_folder['id'];
            $result = $this->sync_post_folder($post_folder, $root_folder_id, $force);

            foreach (array('created', 'updated', 'skipped') as $key) {
                $results[$key] += (int) ($result[$key] ?? 0);
            }
            if (! empty($result['error'])) {
                $results['errors'][] = $result['error'];
            }
        }

        $results['missing'] += $this->repository->mark_missing_post_folders($root_folder_id, $seen_folder_ids);

        return $results;
    }

    /**
     * @param array{name?: string, id: string} $folder
     * @param string                           $root_folder_id
     * @param bool                             $force
     * @return array{created: int, updated: int, skipped: int, error: string}
     */
    private function sync_post_folder(array $folder, $root_folder_id, $force = false)
    {
        $result = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => '');

        $files = $this->google_client->list_files_in_folder($folder['id']);
        if (is_wp_error($files)) {
            $result['error'] = sprintf('%s: %s', $folder['name'], $files->get_error_message());

            return $result;
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
                $is_named = 0 === stripos((string) ($file['name'] ?? ''), 'featured');
                if ($is_image || $is_named) {
                    $image_file = $file;
                }
            }
        }

        if (null === $doc_file) {
            return $result;
        }

        $post_id = $this->repository->post_id_for_folder($folder['id']);

        $html = $this->google_client->export_doc_html($doc_file['id']);
        if (is_wp_error($html)) {
            $result['error'] = sprintf('%s: %s', $folder['name'], $html->get_error_message());

            return $result;
        }

        $parsed = AutoDocs_Doc_Meta::parse_and_strip($html);
        $body_html = $parsed['body_html'];
        $sanitized_body = $this->media->sanitize_google_html($body_html);
        $body_hash = hash('sha256', $body_html);

        if ($post_id && ! $force) {
            $saved_mod = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_MODIFIED, true);
            $saved_hash = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_CONTENT_HASH, true);
            $file_mod = (string) ($doc_file['modifiedTime'] ?? '');
            if ($saved_mod === $file_mod && $saved_hash !== '' && hash_equals($saved_hash, $body_hash)) {
                update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, 'synced');
                ++$result['skipped'];

                return $result;
            }
        }

        $acf_field = trim((string) $this->settings->get('acf_body_field', ''));
        $use_acf = ($acf_field !== '' && function_exists('update_field'));
        $postarr = array(
            'post_title' => sanitize_text_field($folder['name']),
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_content' => $use_acf ? ' ' : $sanitized_body,
        );

        if ($post_id) {
            $postarr['ID'] = $post_id;
            $saved_id = wp_update_post(wp_slash($postarr), true);
            $action = 'updated';
        } else {
            $saved_id = wp_insert_post(wp_slash($postarr), true);
            $action = 'created';
        }

        if (is_wp_error($saved_id)) {
            $result['error'] = sprintf('%s: %s', $folder['name'], $saved_id->get_error_message());

            return $result;
        }

        if ($use_acf) {
            update_field($acf_field, $sanitized_body, (int) $saved_id);
        }

        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_FILE_ID, $doc_file['id']);
        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_FOLDER_ID, $folder['id']);
        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_MODIFIED, $doc_file['modifiedTime']);
        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_STATUS, 'synced');
        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_LAST_SYNCED, current_time('mysql'));
        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_CONTENT_HASH, $body_hash);
        update_post_meta($saved_id, AutoDocs_Sync_Meta::META_SOURCE_ROOT, $root_folder_id);
        if ('created' === $action && ! get_post_meta($saved_id, AutoDocs_Sync_Meta::META_FIRST_IMPORTED, true)) {
            update_post_meta($saved_id, AutoDocs_Sync_Meta::META_FIRST_IMPORTED, current_time('mysql'));
        }

        if ($image_file) {
            $this->media->set_featured_image_from_drive($saved_id, $image_file, $html);
        } else {
            $this->media->maybe_set_featured_image_from_html($saved_id, $html);
        }

        ++$result[$action];

        return $result;
    }
}
