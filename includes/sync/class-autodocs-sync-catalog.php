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
        $paged = $this->list_bucket_articles_paged($bucket_id, $bucket_label, 1, PHP_INT_MAX);

        return is_wp_error($paged) ? $paged : $paged['articles'];
    }

    /**
     * Paginated article rows under a bucket.
     *
     * @return array{articles: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}|WP_Error
     */
    public function list_bucket_articles_paged($bucket_id, $bucket_label, $page, $per_page)
    {
        $bucket_id = is_string($bucket_id) ? trim($bucket_id) : '';
        if ($bucket_id === '') {
            return array(
                'articles' => array(),
                'total' => 0,
                'page' => 1,
                'per_page' => max(1, (int) $per_page),
                'total_pages' => 0,
            );
        }

        $page = max(1, (int) $page);
        $per_page = max(1, min(50, (int) $per_page));
        $bucket_label = is_string($bucket_label) ? $bucket_label : '';

        $folders = $this->google_client->list_folders($bucket_id);
        if (is_wp_error($folders)) {
            return $folders;
        }

        $total = 0;
        $offset = ($page - 1) * $per_page;
        $articles = array();

        foreach ($folders as $fd) {
            $fid = isset($fd['id']) ? (string) $fd['id'] : '';
            if ($fid === '') {
                continue;
            }
            $fname = isset($fd['name']) ? (string) $fd['name'] : $fid;

            $row = $this->build_article_row_for_folder($fid, $fname, $bucket_label, true);
            if (null === $row) {
                continue;
            }

            ++$total;
            if ($total > $offset && count($articles) < $per_page) {
                $articles[] = $row;
            }
        }

        $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;

        return array(
            'articles' => $articles,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        );
    }

    /**
     * Count article folders in a bucket (no HTML export).
     *
     * @return int|WP_Error
     */
    public function count_bucket_articles($bucket_id)
    {
        $bucket_id = is_string($bucket_id) ? trim($bucket_id) : '';
        if ($bucket_id === '') {
            return 0;
        }

        $folders = $this->google_client->list_folders($bucket_id);
        if (is_wp_error($folders)) {
            return $folders;
        }

        $count = 0;
        foreach ($folders as $fd) {
            $fid = isset($fd['id']) ? (string) $fd['id'] : '';
            if ($fid === '') {
                continue;
            }
            $fname = isset($fd['name']) ? (string) $fd['name'] : $fid;
            $row = $this->build_article_row_for_folder($fid, $fname, '', false);
            if (null !== $row) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Article folders in the Synced bucket whose Google Doc changed since the last WordPress sync.
     *
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public function list_modified_articles_from_synced_bucket($bucket_label = '')
    {
        $paged = $this->list_modified_articles_paged($bucket_label, 1, PHP_INT_MAX);

        return is_wp_error($paged) ? $paged : $paged['articles'];
    }

    /**
     * Paginated modified articles (WordPress status meta; Drive details only for current page).
     *
     * @return array{articles: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}|WP_Error
     */
    public function list_modified_articles_paged($bucket_label, $page, $per_page)
    {
        $synced = (string) $this->settings->get('folder_synced', '');
        if ($synced === '') {
            return array(
                'articles' => array(),
                'total' => 0,
                'page' => 1,
                'per_page' => max(1, (int) $per_page),
                'total_pages' => 0,
            );
        }

        $page = max(1, (int) $page);
        $per_page = max(1, min(50, (int) $per_page));
        $bucket_label = is_string($bucket_label) ? $bucket_label : '';

        $this->status->refresh_statuses_for_working_folder($synced, false);

        $total = $this->repository->count_posts_by_sync_status('modified');
        $offset = ($page - 1) * $per_page;
        $folder_ids = $this->repository->folder_ids_by_sync_status('modified', $per_page, $offset);

        $articles = array();
        foreach ($folder_ids as $fid) {
            $row = $this->build_article_row_for_folder($fid, '', $bucket_label, true);
            if (null !== $row) {
                $articles[] = $row;
            }
        }

        $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;

        return array(
            'articles' => $articles,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        );
    }

    /**
     * @return int
     */
    public function count_modified_articles_in_synced_bucket()
    {
        $synced = (string) $this->settings->get('folder_synced', '');
        if ($synced !== '') {
            $this->status->refresh_statuses_for_working_folder($synced, false);
        }

        return $this->repository->count_posts_by_sync_status('modified');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build_article_row_for_folder($folder_id, $folder_name, $bucket_label, $include_doc_meta)
    {
        $folder_id = is_string($folder_id) ? trim($folder_id) : '';
        if ($folder_id === '') {
            return null;
        }

        $files = $this->google_client->list_files_in_folder($folder_id);
        if (is_wp_error($files)) {
            return null;
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
            return null;
        }

        $fname = $folder_name !== '' ? $folder_name : $folder_id;
        if ($fname === $folder_id) {
            $meta = $this->google_client->get_file_meta($folder_id);
            if (! is_wp_error($meta) && ! empty($meta['name'])) {
                $fname = (string) $meta['name'];
            }
        }

        $thumb = '';
        if ($image_file && ! empty($image_file['thumbnailLink'])) {
            $thumb = (string) $image_file['thumbnailLink'];
        } elseif (! empty($doc_file['thumbnailLink'])) {
            $thumb = (string) $doc_file['thumbnailLink'];
        }

        $size = isset($doc_file['size']) ? (int) $doc_file['size'] : 0;
        $meta_cats = '';

        if ($include_doc_meta) {
            $html_export = $this->google_client->export_doc_html($doc_file['id']);
            if (! is_wp_error($html_export) && is_string($html_export)) {
                $doc_id = isset($doc_file['id']) ? (string) $doc_file['id'] : '';
                $parsed_row = AutoDocs_Doc_Meta::extract_from_export($this->google_client, $doc_id, $html_export);
                $mr = $parsed_row['meta'];
                if (! empty($mr['categories'])) {
                    $meta_cats = (string) $mr['categories'];
                }
            }
        }

        $pid = $this->repository->post_id_for_folder($folder_id);
        $last_wp = '';
        $last_wp_formatted = '';
        $edit_url = '';
        if ($pid) {
            $last_wp = (string) get_post_meta($pid, AutoDocs_Sync_Meta::META_LAST_SYNCED, true);
            if ($last_wp !== '') {
                $last_wp_formatted = mysql2date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $last_wp
                );
            }
            $edit = get_edit_post_link($pid, 'raw');
            $edit_url = $edit ? (string) $edit : '';
        }

        return array(
            'folder_id' => $folder_id,
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
            'post_id' => $pid,
            'edit_url' => $edit_url,
        );
    }
}
