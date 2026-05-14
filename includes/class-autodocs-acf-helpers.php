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
     * @return array<int, array{value: string, label: string, group: string}>
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
     * @param array<int, array{value: string, label: string, group: string}> $out
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
                );
            }

            if ($type === 'group' && ! empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                self::walk_fields($field['sub_fields'], $group_title, $path, $allowed_types, $seen, $out);
            }
        }
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
