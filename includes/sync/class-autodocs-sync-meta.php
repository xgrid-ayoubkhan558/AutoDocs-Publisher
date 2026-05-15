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
    public const META_MODIFIED = '_autodocs_google_modified_time';
    public const META_STATUS = '_autodocs_sync_status';
    public const META_LAST_SYNCED = '_autodocs_last_synced_time';
    public const META_CONTENT_HASH = '_autodocs_content_hash';
    public const META_SOURCE_ROOT = '_autodocs_source_root_folder';
    public const META_FIRST_IMPORTED = '_autodocs_first_imported_time';

    /** Site-wide timestamp (mysql) updated when bulk sync completes. */
    public const OPTION_LAST_SITE_SYNC = 'autodocs_publisher_last_site_sync';

    public const MIME_DOC = 'application/vnd.google-apps.document';
    public const MIME_FOLDER = 'application/vnd.google-apps.folder';
}
