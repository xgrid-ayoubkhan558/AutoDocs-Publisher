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
}
