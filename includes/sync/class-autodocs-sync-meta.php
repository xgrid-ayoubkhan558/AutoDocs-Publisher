<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Post meta keys and Drive MIME identifiers used across sync/import flows.
 */
final class AutoDocs_Sync_Meta
{
    public const META_FILE_ID = '_autodocs_google_file_id';
    public const META_FOLDER_ID = '_autodocs_google_folder_id';
    public const META_FOLDER_NAME = '_autodocs_google_folder_name';
    public const META_MODIFIED = '_autodocs_google_modified_time';
    public const META_STATUS = '_autodocs_sync_status';
    public const META_LAST_SYNCED = '_autodocs_last_synced_time';
    public const META_LAST_SYNC_SOURCE = '_autodocs_last_sync_source';

    public const SYNC_SOURCE_CRON = 'cron';
    public const SYNC_SOURCE_MANUAL = 'manual';
    public const SYNC_SOURCE_IMPORT = 'import';
    public const META_CONTENT_HASH = '_autodocs_content_hash';
    public const META_SOURCE_ROOT = '_autodocs_source_root_folder';
    public const META_FIRST_IMPORTED = '_autodocs_first_imported_time';

    /** Site-wide timestamp (mysql) updated when bulk sync completes. */
    public const OPTION_LAST_SITE_SYNC = 'autodocs_publisher_last_site_sync';

    /** Timestamp (mysql) when the scheduled cron job last ran. */
    public const OPTION_LAST_CRON_RUN = 'autodocs_publisher_last_cron_run';

    public const MIME_DOC = 'application/vnd.google-apps.document';
    public const MIME_FOLDER = 'application/vnd.google-apps.folder';

    /**
     * @param string $source
     * @return string
     */
    public static function sync_source_label($source)
    {
        switch ((string) $source) {
            case self::SYNC_SOURCE_CRON:
                return __('Automatic (cron)', 'autodocs-publisher');
            case self::SYNC_SOURCE_IMPORT:
                return __('Import', 'autodocs-publisher');
            case self::SYNC_SOURCE_MANUAL:
                return __('Manual sync', 'autodocs-publisher');
            default:
                return '';
        }
    }
}
