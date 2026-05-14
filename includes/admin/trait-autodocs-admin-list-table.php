<?php

if (!defined('ABSPATH')) {
    exit;
}

trait AutoDocs_Admin_List_Table_Trait
{
    /**
     * @return string[]
     */
    private function autodocs_list_screen_post_types()
    {
        static $types = null;
        if (null === $types) {
            $types = get_post_types(array('show_ui' => true), 'names');
        }

        return $types;
    }

    public function columns($columns)
    {
        $out = array();
        $inserted = false;
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ('title' === $key) {
                $out['autodocs_from'] = __('Content source', 'autodocs-publisher');
                $out['autodocs_first_imported'] = __('First imported', 'autodocs-publisher');
                $inserted = true;
            }
        }
        if (! $inserted) {
            $out = array(
                'autodocs_from' => __('Content source', 'autodocs-publisher'),
                'autodocs_first_imported' => __('First imported', 'autodocs-publisher'),
            ) + $out;
        }
        $out['autodocs_sync_status'] = __('Sync status', 'autodocs-publisher');
        $out['autodocs_last_synced'] = __('Last synced', 'autodocs-publisher');

        return $out;
    }

    public function column_content($column, $post_id)
    {
        if ('autodocs_from' === $column) {
            $from_docs = get_post_meta($post_id, AutoDocs_Sync_Meta::META_FOLDER_ID, true)
                || get_post_meta($post_id, AutoDocs_Sync_Meta::META_FILE_ID, true);
            echo $from_docs
                ? esc_html__('Google Docs', 'autodocs-publisher')
                : esc_html__('WordPress', 'autodocs-publisher');

            return;
        }

        if ('autodocs_first_imported' === $column) {
            $first = get_post_meta($post_id, AutoDocs_Sync_Meta::META_FIRST_IMPORTED, true);
            echo $first
                ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $first))
                : '&mdash;';

            return;
        }

        if ('autodocs_sync_status' === $column) {
            echo wp_kses_post($this->status_badge(get_post_meta($post_id, AutoDocs_Sync_Meta::META_STATUS, true)));

            return;
        }

        if ('autodocs_last_synced' === $column) {
            $last_synced = get_post_meta($post_id, AutoDocs_Sync_Meta::META_LAST_SYNCED, true);
            echo $last_synced ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $last_synced)) : '&mdash;';
        }
    }

    public function status_filter()
    {
        global $typenow;
        if (! in_array($typenow, $this->autodocs_list_screen_post_types(), true)) {
            return;
        }

        $current = isset($_GET['autodocs_sync_status']) ? sanitize_text_field(wp_unslash($_GET['autodocs_sync_status'])) : '';
        ?>
        <select name="autodocs_sync_status">
            <option value=""><?php esc_html_e('All sync statuses', 'autodocs-publisher'); ?></option>
            <?php foreach (array('synced', 'new', 'modified', 'missing') as $status) : ?>
                <option value="<?php echo esc_attr($status); ?>" <?php selected($current, $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function apply_status_filter($query)
    {
        $post_type = $query->get('post_type') ? $query->get('post_type') : 'post';
        if (! is_admin() || ! $query->is_main_query() || ! in_array($post_type, $this->autodocs_list_screen_post_types(), true) || empty($_GET['autodocs_sync_status'])) {
            return;
        }

        $status = sanitize_text_field(wp_unslash($_GET['autodocs_sync_status']));
        if ('new' === $status) {
            $query->set('meta_query', array(
                array(
                    'key' => AutoDocs_Sync_Meta::META_FILE_ID,
                    'compare' => 'NOT EXISTS',
                ),
            ));
            return;
        }

        if (in_array($status, array('synced', 'modified', 'missing'), true)) {
            $query->set('meta_key', AutoDocs_Sync_Meta::META_STATUS);
            $query->set('meta_value', $status);
        }
    }

    private function status_badge($status)
    {
        $status = $status ? $status : 'new';
        $labels = array(
            'synced' => __('Synced', 'autodocs-publisher'),
            'new' => __('New', 'autodocs-publisher'),
            'modified' => __('Modified', 'autodocs-publisher'),
            'missing' => __('Missing', 'autodocs-publisher'),
        );

        $label = isset($labels[$status]) ? $labels[$status] : $labels['new'];
        return sprintf('<span class="autodocs-status autodocs-status-%s">%s</span>', esc_attr($status), esc_html($label));
    }
}
