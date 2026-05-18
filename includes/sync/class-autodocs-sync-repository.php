<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WordPress post queries and updates for Drive-linked posts.
 */
final class AutoDocs_Sync_Repository
{
    /**
     * @param string $folder_id
     * @return int Post ID or 0
     */
    public function post_id_for_folder($folder_id)
    {
        $posts = get_posts(array(
            'post_type' => 'any',
            'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'meta_key' => AutoDocs_Sync_Meta::META_FOLDER_ID,
            'meta_value' => $folder_id,
            'fields' => 'ids',
            'posts_per_page' => 1,
        ));

        return $posts ? (int) $posts[0] : 0;
    }

    /**
     * @param string $root_folder_id
     * @return int[]
     */
    public function tracked_posts_by_root($root_folder_id)
    {
        return get_posts(array(
            'post_type' => 'any',
            'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'meta_key' => AutoDocs_Sync_Meta::META_SOURCE_ROOT,
            'meta_value' => $root_folder_id,
            'fields' => 'ids',
            'posts_per_page' => -1,
        ));
    }

    /**
     * @param string       $root_folder_id
     * @param list<string> $seen_folder_ids
     * @return int Number of posts marked missing
     */
    public function mark_missing_post_folders($root_folder_id, array $seen_folder_ids)
    {
        $count = 0;
        foreach ($this->tracked_posts_by_root($root_folder_id) as $post_id) {
            $folder_id = get_post_meta($post_id, AutoDocs_Sync_Meta::META_FOLDER_ID, true);
            if ($folder_id && ! in_array($folder_id, $seen_folder_ids, true)) {
                update_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, 'missing');
                wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param string $status
     * @return int
     */
    public function count_posts_by_sync_status($status)
    {
        $status = is_string($status) ? trim($status) : '';
        if ($status === '') {
            return 0;
        }

        $q = new WP_Query(
            array(
                'post_type' => 'any',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'meta_key' => AutoDocs_Sync_Meta::META_STATUS,
                'meta_value' => $status,
                'fields' => 'ids',
                'posts_per_page' => 1,
                'no_found_rows' => false,
            )
        );

        return (int) $q->found_posts;
    }

    /**
     * @param string $status
     * @param int    $limit  Max IDs to return; -1 for all.
     * @param int    $offset
     * @return list<string>
     */
    public function folder_ids_by_sync_status($status, $limit = -1, $offset = 0)
    {
        $status = is_string($status) ? trim($status) : '';
        if ($status === '') {
            return array();
        }

        $per_page = $limit > 0 ? $limit : -1;
        $posts = get_posts(
            array(
                'post_type' => 'any',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'meta_key' => AutoDocs_Sync_Meta::META_STATUS,
                'meta_value' => $status,
                'fields' => 'ids',
                'posts_per_page' => $per_page,
                'offset' => max(0, (int) $offset),
                'orderby' => 'ID',
                'order' => 'DESC',
            )
        );

        $ids = array();
        foreach ($posts as $post_id) {
            $fid = get_post_meta((int) $post_id, AutoDocs_Sync_Meta::META_FOLDER_ID, true);
            if (is_string($fid) && $fid !== '') {
                $ids[] = $fid;
            }
        }

        return $ids;
    }

    /**
     * Most recent per-post sync time (mysql), for display when site option is unset.
     *
     * @return string
     */
    public function latest_last_synced_mysql()
    {
        $posts = get_posts(
            array(
                'post_type' => 'any',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'meta_key' => AutoDocs_Sync_Meta::META_LAST_SYNCED,
                'orderby' => 'meta_value',
                'order' => 'DESC',
                'posts_per_page' => 1,
                'fields' => 'ids',
            )
        );

        if (! $posts) {
            return '';
        }

        return (string) get_post_meta((int) $posts[0], AutoDocs_Sync_Meta::META_LAST_SYNCED, true);
    }

    /**
     * @param int $limit
     * @return array<int, array{post_id: int, title: string, edit_url: string, last_synced_formatted: string, post_type: string}>
     */
    public function list_recent_synced_posts($limit = 10)
    {
        $limit = max(1, min(25, (int) $limit));

        $posts = get_posts(
            array(
                'post_type' => 'any',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'meta_key' => AutoDocs_Sync_Meta::META_LAST_SYNCED,
                'orderby' => 'meta_value',
                'order' => 'DESC',
                'posts_per_page' => $limit,
                'fields' => 'ids',
            )
        );

        $out = array();
        foreach ($posts as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0) {
                continue;
            }
            $last = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_LAST_SYNCED, true);
            $formatted = $last !== ''
                ? (string) mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last)
                : '';
            $edit = get_edit_post_link($post_id, 'raw');
            $out[] = array(
                'post_id' => $post_id,
                'title' => get_the_title($post_id),
                'edit_url' => $edit ? (string) $edit : '',
                'last_synced_formatted' => $formatted,
                'post_type' => get_post_type($post_id) ?: '',
            );
        }

        return $out;
    }
}
