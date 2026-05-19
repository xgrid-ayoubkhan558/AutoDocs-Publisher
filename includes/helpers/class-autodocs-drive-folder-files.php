<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pick the Google Doc and optional featured image from a Drive folder listing.
 */
final class AutoDocs_Drive_Folder_Files
{
    /**
     * @param array<int, array<string, mixed>> $files From Google_Client::list_files_in_folder().
     * @return array{doc: array<string, mixed>, image: array<string, mixed>|null}|WP_Error
     */
    public static function pick_doc_and_featured_image(array $files)
    {
        $doc_file = null;
        $image_file = null;

        foreach ($files as $file) {
            if (AutoDocs_Sync_Meta::MIME_DOC === ($file['mimeType'] ?? '') && null === $doc_file) {
                $doc_file = $file;
            }
            if (null === $image_file && self::is_featured_image_candidate($file)) {
                $image_file = $file;
            }
        }

        if (null === $doc_file) {
            return new WP_Error('autodocs_no_doc', __('No Google Doc found in this folder.', 'autodocs-publisher'));
        }

        return array(
            'doc' => $doc_file,
            'image' => $image_file,
        );
    }

    /**
     * Drive file rows for the import wizard file list.
     *
     * @param array<int, array<string, mixed>> $files
     * @return array<int, array<string, mixed>>
     */
    public static function files_for_admin_preview(array $files)
    {
        $drive_files = array();
        foreach ($files as $file) {
            $fname = isset($file['name']) ? (string) $file['name'] : '';
            $mt = isset($file['mimeType']) ? (string) $file['mimeType'] : '';
            $drive_files[] = array(
                'id' => isset($file['id']) ? (string) $file['id'] : '',
                'name' => $fname !== '' ? $fname : __('(unnamed)', 'autodocs-publisher'),
                'mimeType' => $mt,
                'size' => isset($file['size']) ? (int) $file['size'] : 0,
                'web_view_link' => isset($file['webViewLink']) ? (string) $file['webViewLink'] : '',
                'thumbnail_url' => isset($file['thumbnailLink']) ? (string) $file['thumbnailLink'] : '',
            );
        }

        return $drive_files;
    }

    /**
     * @param array<string, mixed> $file
     * @return bool
     */
    private static function is_featured_image_candidate(array $file)
    {
        $is_image = in_array(
            $file['mimeType'] ?? '',
            array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
            true
        );
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $is_named = $name !== '' && 0 === stripos($name, 'featured');

        return $is_image || $is_named;
    }
}
