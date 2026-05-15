<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-doc-meta.php';

/**
 * Drive vs WordPress sync status (modified / synced / missing) for linked posts.
 */
final class AutoDocs_Sync_Status_Manager
{
    /** @var AutoDocs_Settings */
    private $settings;

    /** @var AutoDocs_Google_Client */
    private $google_client;

    /** @var AutoDocs_Sync_Repository */
    private $repository;

    public function __construct(
        AutoDocs_Settings $settings,
        AutoDocs_Google_Client $google_client,
        AutoDocs_Sync_Repository $repository
    ) {
        $this->settings = $settings;
        $this->google_client = $google_client;
        $this->repository = $repository;
    }

    /**
     * Unique non-empty Drive folder ids configured as New / Synced buckets.
     *
     * @return string[]
     */
    public function status_bucket_folder_ids()
    {
        $ids = array(
            (string) $this->settings->get('folder_new', ''),
            (string) $this->settings->get('folder_synced', ''),
        );

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    public function refresh_known_statuses()
    {
        $working_folder = $this->settings->get('working_folder_id', '');
        $bucket_ids = $this->status_bucket_folder_ids();

        if (! empty($bucket_ids)) {
            foreach ($bucket_ids as $bucket_id) {
                $this->refresh_statuses_for_working_folder($bucket_id, false);
            }
        } elseif ($working_folder) {
            $this->refresh_statuses_for_working_folder($working_folder, true);
        }
    }

    /**
     * @param string $root_folder_id
     * @param bool   $filter_status_subfolders
     */
    public function refresh_statuses_for_working_folder($root_folder_id, $filter_status_subfolders = true)
    {
        $post_folders = $this->google_client->list_folders($root_folder_id);
        if (is_wp_error($post_folders)) {
            return;
        }

        $status_folder_ids = array();
        if ($filter_status_subfolders) {
            $status_folder_ids = array_filter(array(
                $this->settings->get('folder_synced'),
                $this->settings->get('folder_new'),
                $this->settings->get('folder_missing'),
            ));
        }

        $remote_folder_ids = array();
        foreach ($post_folders as $f) {
            if ($filter_status_subfolders && in_array($f['id'], $status_folder_ids, true)) {
                continue;
            }
            $remote_folder_ids[] = $f['id'];
        }

        foreach ($this->repository->tracked_posts_by_root($root_folder_id) as $post_id) {
            $folder_id = get_post_meta($post_id, AutoDocs_Sync_Meta::META_FOLDER_ID, true);
            if (! $folder_id || ! in_array($folder_id, $remote_folder_ids, true)) {
                update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, 'missing');
                continue;
            }

            $files = $this->google_client->list_files_in_folder($folder_id);
            if (is_wp_error($files)) {
                continue;
            }
            foreach ($files as $file) {
                if (AutoDocs_Sync_Meta::MIME_DOC === ($file['mimeType'] ?? '')) {
                    $saved_mod = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_MODIFIED, true);
                    $saved_hash = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_CONTENT_HASH, true);
                    $file_mod = (string) ($file['modifiedTime'] ?? '');
                    if ($saved_mod !== $file_mod) {
                        update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, 'modified');
                        break;
                    }
                    if ($saved_hash === '') {
                        update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, 'modified');
                        break;
                    }
                    $html = $this->google_client->export_doc_html($file['id']);
                    if (is_wp_error($html)) {
                        break;
                    }
                    $body = AutoDocs_Doc_Meta::extract_from_export($this->google_client, $file['id'], $html)['body_html'];
                    $h = hash('sha256', $body);
                    update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, hash_equals($saved_hash, $h) ? 'synced' : 'modified');
                    break;
                }
            }
        }
    }
}
