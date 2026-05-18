<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Discover ACF fields suitable for mapping imported HTML (WYSIWYG / textarea, including inside groups).
 */
final class AutoDocs_Acf_Helpers
{
    /** Select option value: save value from companion text field instead. */
    const SELECT_CUSTOM_VALUE = '__autodocs_custom__';

    /** Use the field configured under plugin Settings (import UI). */
    const SELECT_SITE_DEFAULT_VALUE = '__autodocs_site_default__';

    /**
     * @return string[]
     */
    public static function mappable_field_types()
    {
        $types = array('wysiwyg', 'textarea', 'acf_code');

        /**
         * @param string[] $types
         * @return string[]
         */
        return apply_filters('autodocs_acf_body_field_types', $types);
    }

    /**
     * Flat list of fields for the settings / import dropdowns.
     *
     * @param string $post_type Optional. When set, prefers `acf_get_field_groups( array( 'post_type' => … ) )`
     *                          as in ACF docs; if that returns nothing, falls back to filtering all active
     *                          groups with `acf_filter_field_groups()`; if still empty, uses all active groups.
     * @return array<int, array{value: string, label: string, group: string, name: string}>
     */
    public static function list_body_target_fields($post_type = '')
    {
        if (! function_exists('acf_get_field_groups') || ! function_exists('acf_get_fields')) {
            return array();
        }

        $groups = acf_get_field_groups(array('active' => true));
        if (! is_array($groups)) {
            $groups = array();
        }

        $requested_pt = '';
        if (is_string($post_type) && $post_type !== '') {
            $requested_pt = sanitize_key($post_type);
            if ($requested_pt !== '' && post_type_exists($requested_pt)) {
                $by_pt = acf_get_field_groups(array(
                    'active' => true,
                    'post_type' => $requested_pt,
                ));
                if (is_array($by_pt) && $by_pt !== array()) {
                    $groups = $by_pt;
                } elseif (function_exists('acf_filter_field_groups')) {
                    $for_filter = self::hydrate_acf_field_groups($groups);
                    $filtered = acf_filter_field_groups(
                        $for_filter,
                        array('post_type' => $requested_pt)
                    );
                    if (is_array($filtered) && $filtered !== array()) {
                        $groups = $filtered;
                    }
                }
            }
        }

        $groups = apply_filters('autodocs_acf_body_field_groups_before_fields', $groups, $requested_pt);
        $allowed = self::mappable_field_types();
        $out = array();
        $seen = array();

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $gtitle = isset($group['title']) ? (string) $group['title'] : '';
            if ($gtitle === '' && ! empty($group['key'])) {
                $gtitle = (string) $group['key'];
            }

            $fields = null;
            if (! empty($group['ID'])) {
                $fields = acf_get_fields((int) $group['ID']);
            }
            if ((! is_array($fields) || $fields === array()) && ! empty($group['key'])) {
                $fields = acf_get_fields((string) $group['key']);
            }
            if (! is_array($fields)) {
                continue;
            }

            self::walk_fields($fields, $gtitle, '', $allowed, $seen, $out);
        }

        return $out;
    }

    /**
     * Load full field group arrays so location rules are present for acf_filter_field_groups().
     *
     * @param array<int, mixed> $groups
     * @return array<int, array<string, mixed>>
     */
    private static function hydrate_acf_field_groups(array $groups)
    {
        if (! function_exists('acf_get_field_group')) {
            return $groups;
        }
        $out = array();
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $loaded = false;
            if (! empty($group['ID'])) {
                $loaded = acf_get_field_group((int) $group['ID']);
            }
            if ((! is_array($loaded) || $loaded === array()) && ! empty($group['key']) && is_string($group['key'])) {
                $loaded = acf_get_field_group($group['key']);
            }
            $out[] = (is_array($loaded) && $loaded !== array()) ? $loaded : $group;
        }

        return $out;
    }

    /**
     * @param array<int, mixed>        $fields
     * @param string                   $group_title
     * @param string                   $prefix
     * @param string[]                 $allowed_types
     * @param array<string, true>      $seen
     * @param array<int, array{value: string, label: string, group: string, name: string}> $out
     */
    private static function walk_fields(array $fields, $group_title, $prefix, array $allowed_types, array &$seen, array &$out)
    {
        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['name'])) {
                continue;
            }
            $type = isset($field['type']) ? (string) $field['type'] : '';
            $flabel = isset($field['label']) ? (string) $field['label'] : (string) $field['name'];
            $path = $prefix === '' ? $flabel : $prefix . ' › ' . $flabel;

            if (in_array($type, $allowed_types, true)) {
                $key = '';
                if (! empty($field['key']) && is_string($field['key'])) {
                    $key = $field['key'];
                }
                if ($key === '') {
                    $key = (string) $field['name'];
                }
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = array(
                    'value' => $key,
                    'label' => $path,
                    'group' => $group_title,
                    'name' => (string) $field['name'],
                );
            }

            if ($type === 'group' && ! empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                self::walk_fields($field['sub_fields'], $group_title, $path, $allowed_types, $seen, $out);
            }
        }
    }

    /**
     * Default ACF field (name or key) for imported HTML per post type.
     *
     * @return array<string, string> Post type slug => field name/key.
     */
    public static function import_body_field_map()
    {
        $map = array(
            'post' => 'paragraph_content_to_the_left_of_sidebar',
            'posts' => 'paragraph_content_to_the_left_of_sidebar',
            'success-stories' => 'add_success_story_detail_description',
            'webinars' => 'why_this_webinar-paragraph',
            'podcasts' => 'detail_description',
            'xgrid-talks' => 'detail_description',
            'white-papers' => 'main_content',
            'wiki' => 'detail_description',
        );

        /**
         * @param array<string, string> $map
         * @return array<string, string>
         */
        return apply_filters('autodocs_import_acf_body_field_by_post_type', $map);
    }

    /**
     * @param string $post_type
     * @return string Field name/key, or empty when no mapping.
     */
    public static function default_body_field_for_post_type($post_type)
    {
        $pt = sanitize_key((string) $post_type);
        if ($pt === '' || ! post_type_exists($pt)) {
            $pt = 'post';
        }
        $map = self::import_body_field_map();

        return isset($map[$pt]) ? (string) $map[$pt] : '';
    }

    /**
     * Find ACF field key (selector) by field name across all active groups.
     *
     * @param string $field_name
     * @return string Field key or name, or empty.
     */
    public static function find_field_selector_by_name($field_name)
    {
        $field_name = trim((string) $field_name);
        if ($field_name === '' || ! function_exists('acf_get_field_groups') || ! function_exists('acf_get_fields')) {
            return '';
        }

        if (function_exists('acf_get_field_object')) {
            $field = acf_get_field_object($field_name);
            if (is_array($field) && ! empty($field['key'])) {
                return (string) $field['key'];
            }
        }

        $groups = acf_get_field_groups(array('active' => true));
        if (! is_array($groups)) {
            return '';
        }

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $fields = null;
            if (! empty($group['ID'])) {
                $fields = acf_get_fields((int) $group['ID']);
            }
            if ((! is_array($fields) || $fields === array()) && ! empty($group['key'])) {
                $fields = acf_get_fields((string) $group['key']);
            }
            if (! is_array($fields)) {
                continue;
            }
            $found = self::find_field_selector_in_fields($fields, $field_name);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    /**
     * @param array<int, mixed> $fields
     * @param string            $field_name
     * @return string
     */
    private static function find_field_selector_in_fields(array $fields, $field_name)
    {
        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['name'])) {
                continue;
            }
            if ((string) $field['name'] === $field_name) {
                if (! empty($field['key']) && is_string($field['key'])) {
                    return (string) $field['key'];
                }

                return (string) $field['name'];
            }
            if (! empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $found = self::find_field_selector_in_fields($field['sub_fields'], $field_name);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * Resolve import UI selection for "Imported HTML goes to" from post-type defaults.
     *
     * @param string $post_type
     * @param array<int, array{value: string, label: string, group: string, name?: string}> $choices
     * @return array{acf_body_field: string, acf_body_field_custom: string}
     */
    public static function resolve_body_field_for_import($post_type, array $choices)
    {
        $empty = array(
            'acf_body_field' => '',
            'acf_body_field_custom' => '',
        );

        $target = self::default_body_field_for_post_type($post_type);
        if ($target === '') {
            return $empty;
        }

        foreach ($choices as $row) {
            if (! is_array($row) || empty($row['value'])) {
                continue;
            }
            $value = (string) $row['value'];
            $name = isset($row['name']) ? (string) $row['name'] : '';
            if ($value === $target || $name === $target) {
                return array(
                    'acf_body_field' => $value,
                    'acf_body_field_custom' => '',
                );
            }
        }

        $selector = self::find_field_selector_by_name($target);
        if ($selector !== '') {
            $vals = self::choice_values($choices);
            if ($vals === array() || in_array($selector, $vals, true)) {
                return array(
                    'acf_body_field' => $selector,
                    'acf_body_field_custom' => '',
                );
            }
        }

        return array(
            'acf_body_field' => self::SELECT_CUSTOM_VALUE,
            'acf_body_field_custom' => $target,
        );
    }

    /**
     * @param array{acf_body_field: string, acf_body_field_custom: string} $selection
     * @return string Value for update_field().
     */
    public static function body_field_name_from_selection(array $selection)
    {
        $sel = isset($selection['acf_body_field']) ? (string) $selection['acf_body_field'] : '';
        $custom = isset($selection['acf_body_field_custom']) ? (string) $selection['acf_body_field_custom'] : '';
        if ($sel === self::SELECT_CUSTOM_VALUE) {
            return $custom;
        }
        if ($sel === '' || $sel === self::SELECT_SITE_DEFAULT_VALUE) {
            return '';
        }

        return $sel;
    }

    /**
     * @param array<int, array{value: string, label: string, group: string}> $choices
     * @return string[]
     */
    public static function choice_values(array $choices)
    {
        $v = array();
        foreach ($choices as $row) {
            if (! empty($row['value'])) {
                $v[] = (string) $row['value'];
            }
        }

        return $v;
    }
}
