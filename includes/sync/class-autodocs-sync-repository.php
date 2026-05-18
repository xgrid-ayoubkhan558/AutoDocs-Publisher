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
     * @return array<int, array{post_id: int, title: string, edit_url: string, last_synced_formatted: string, post_type: string, sync_source: string, sync_source_label: string}>
     */
    public function list_recent_synced_posts($limit = 10)
    {
        $limit = max(1, min(25, (int) $limit));

        $query = new WP_Query(
            array(
                'post_type' => 'any',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'posts_per_page' => $limit * 3,
                'fields' => 'ids',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'meta_query' => array(
                    array(
                        'key' => AutoDocs_Sync_Meta::META_LAST_SYNCED,
                        'compare' => 'EXISTS',
                    ),
                ),
                'orderby' => 'meta_value',
                'meta_key' => AutoDocs_Sync_Meta::META_LAST_SYNCED,
                'meta_type' => 'DATETIME',
                'order' => 'DESC',
            )
        );

        $seen = array();
        $rows = array();

        foreach ($query->posts as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0 || isset($seen[ $post_id ])) {
                continue;
            }
            $seen[ $post_id ] = true;

            $row = $this->build_recent_sync_row($post_id);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort(
            $rows,
            static function ($a, $b) {
                $cmp = ($b['last_synced_ts'] ?? 0) <=> ($a['last_synced_ts'] ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return ($b['post_id'] ?? 0) <=> ($a['post_id'] ?? 0);
            }
        );

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param int $post_id
     * @return array<string, mixed>|null
     */
    private function build_recent_sync_row($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return null;
        }

        $last = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_LAST_SYNCED, true);
        if ($last === '') {
            return null;
        }

        $last_ts = 0;
        $formatted = '';
        $dt = date_create_from_format('Y-m-d H:i:s', $last, wp_timezone());
        if ($dt) {
            $last_ts = $dt->getTimestamp();
            $formatted = wp_date(
                get_option('date_format') . ' ' . get_option('time_format'),
                $last_ts
            );
        }

        $edit = get_edit_post_link($post_id, 'raw');
        $source = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_LAST_SYNC_SOURCE, true);
        $title = html_entity_decode((string) get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $folder_name = (string) get_post_meta($post_id, AutoDocs_Sync_Meta::META_FOLDER_NAME, true);
        $slug = (string) get_post_field('post_name', $post_id);
        $ptype = get_post_type($post_id) ?: '';
        $ptype_label = '';
        if ($ptype !== '') {
            $pto = get_post_type_object($ptype);
            $ptype_label = $pto && isset($pto->labels->singular_name) ? (string) $pto->labels->singular_name : $ptype;
        }

        $subtitle_parts = array();
        if ($folder_name !== '') {
            $subtitle_parts[] = $folder_name;
        } elseif ($slug !== '') {
            $subtitle_parts[] = $slug;
        }
        if ($ptype_label !== '') {
            $subtitle_parts[] = $ptype_label;
        }

        return array(
            'post_id' => $post_id,
            'title' => $title,
            'subtitle' => implode(' · ', $subtitle_parts),
            'edit_url' => $edit ? (string) $edit : '',
            'last_synced_formatted' => $formatted,
            'last_synced_ts' => $last_ts,
            'post_type' => $ptype,
            'sync_source' => $source,
            'sync_source_label' => AutoDocs_Sync_Meta::sync_source_label($source),
        );
    }
}
