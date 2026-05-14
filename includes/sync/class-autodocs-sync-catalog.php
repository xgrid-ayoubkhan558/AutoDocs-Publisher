<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-doc-meta.php';

/**
 * Drive folder listing for admin UI (New / Synced / Modified tab).
 */
final class AutoDocs_Sync_Catalog
{
    /** @var AutoDocs_Settings */
    private $settings;

    /** @var AutoDocs_Google_Client */
    private $google_client;

    /** @var AutoDocs_Sync_Repository */
    private $repository;

    /** @var AutoDocs_Sync_Status_Manager */
    private $status;

    public function __construct(
        AutoDocs_Settings $settings,
        AutoDocs_Google_Client $google_client,
        AutoDocs_Sync_Repository $repository,
        AutoDocs_Sync_Status_Manager $status
    ) {
        $this->settings = $settings;
        $this->google_client = $google_client;
        $this->repository = $repository;
        $this->status = $status;
    }

    /**
     * Article rows under a bucket (subfolders that contain a Google Doc).
     *
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public function list_bucket_articles_detailed($bucket_id, $bucket_label = '')
    {
        $bucket_id = is_string($bucket_id) ? trim($bucket_id) : '';
        if ($bucket_id === '') {
            return array();
        }

        $bucket_label = is_string($bucket_label) ? $bucket_label : '';

        $folders = $this->google_client->list_folders($bucket_id);
        if (is_wp_error($folders)) {
            return $folders;
        }

        $rows = array();
        foreach ($folders as $fd) {
            $fid = isset($fd['id']) ? (string) $fd['id'] : '';
            if ($fid === '') {
                continue;
            }
            $fname = isset($fd['name']) ? (string) $fd['name'] : $fid;

            $files = $this->google_client->list_files_in_folder($fid);
            if (is_wp_error($files)) {
                continue;
            }

            $doc_file = null;
            $image_file = null;
            foreach ($files as $file) {
                if (null === $doc_file && AutoDocs_Sync_Meta::MIME_DOC === ($file['mimeType'] ?? '')) {
                    $doc_file = $file;
                }
                if (null === $image_file) {
                    $mt = $file['mimeType'] ?? '';
                    $is_image = in_array(
                        $mt,
                        array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                        true
                    );
                    $is_named = isset($file['name']) && 0 === stripos((string) $file['name'], 'featured');
                    if ($is_image || $is_named) {
                        $image_file = $file;
                    }
                }
            }

            if (null === $doc_file) {
                continue;
            }

            $thumb = '';
            if ($image_file && ! empty($image_file['thumbnailLink'])) {
                $thumb = (string) $image_file['thumbnailLink'];
            } elseif (! empty($doc_file['thumbnailLink'])) {
                $thumb = (string) $doc_file['thumbnailLink'];
            }

            $size = isset($doc_file['size']) ? (int) $doc_file['size'] : 0;

            $meta_cats = '';
            $html_export = $this->google_client->export_doc_html($doc_file['id']);
            if (! is_wp_error($html_export) && is_string($html_export)) {
                $parsed_row = AutoDocs_Doc_Meta::parse_and_strip($html_export);
                $mr = $parsed_row['meta'];
                if (! empty($mr['categories'])) {
                    $meta_cats = (string) $mr['categories'];
                }
            }

            $pid = $this->repository->post_id_for_folder($fid);
            $last_wp = '';
            $last_wp_formatted = '';
            if ($pid) {
                $last_wp = (string) get_post_meta($pid, AutoDocs_Sync_Meta::META_LAST_SYNCED, true);
                if ($last_wp !== '') {
                    $last_wp_formatted = mysql2date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $last_wp
                    );
                }
            }

            $rows[] = array(
                'folder_id' => $fid,
                'folder_name' => $fname,
                'doc_id' => $doc_file['id'] ?? '',
                'doc_name' => isset($doc_file['name']) ? (string) $doc_file['name'] : '',
                'modified' => isset($doc_file['modifiedTime']) ? (string) $doc_file['modifiedTime'] : '',
                'size' => $size,
                'web_view_link' => isset($doc_file['webViewLink']) ? (string) $doc_file['webViewLink'] : '',
                'path_label' => trim($bucket_label . ' / ' . $fname),
                'thumbnail_url' => $thumb,
                'categories_display' => $meta_cats,
                'last_synced_wp' => $last_wp,
                'last_synced_formatted' => $last_wp_formatted,
            );
        }

        return $rows;
    }

    /**
     * Article folders in the Synced bucket whose Google Doc changed since the last WordPress sync.
     *
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public function list_modified_articles_from_synced_bucket($bucket_label = '')
    {
        $synced = (string) $this->settings->get('folder_synced', '');
        if ($synced === '') {
            return array();
        }

        $this->status->refresh_statuses_for_working_folder($synced, false);

        $rows = $this->list_bucket_articles_detailed($synced, $bucket_label);
        if (is_wp_error($rows)) {
            return $rows;
        }

        $out = array();
        foreach ($rows as $row) {
            $pid = $this->repository->post_id_for_folder(isset($row['folder_id']) ? (string) $row['folder_id'] : '');
            if (! $pid) {
                continue;
            }
            $st = (string) get_post_meta($pid, AutoDocs_Sync_Meta::META_STATUS, true);
            if ($st === 'modified') {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return int
     */
    public function count_modified_articles_in_synced_bucket()
    {
        $r = $this->list_modified_articles_from_synced_bucket('');

        return is_wp_error($r) ? 0 : count($r);
    }
}
