<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-meta.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-repository.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-media.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-status-manager.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-catalog.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-import.php';
require_once AUTODOCS_PUBLISHER_DIR . 'includes/sync/class-autodocs-sync-engine.php';

/**
 * Facade for sync, status, Drive catalog, and import flows.
 *
 * Subclasses live under includes/sync/ — use those for targeted changes.
 */
class AutoDocs_Sync_Service
{
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_FILE_ID = AutoDocs_Sync_Meta::META_FILE_ID;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_FOLDER_ID = AutoDocs_Sync_Meta::META_FOLDER_ID;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_MODIFIED = AutoDocs_Sync_Meta::META_MODIFIED;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_STATUS = AutoDocs_Sync_Meta::META_STATUS;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_LAST_SYNCED = AutoDocs_Sync_Meta::META_LAST_SYNCED;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_CONTENT_HASH = AutoDocs_Sync_Meta::META_CONTENT_HASH;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_SOURCE_ROOT = AutoDocs_Sync_Meta::META_SOURCE_ROOT;
    /** @deprecated Use AutoDocs_Sync_Meta::META_* */
    const META_FIRST_IMPORTED = AutoDocs_Sync_Meta::META_FIRST_IMPORTED;
    /** @deprecated Use AutoDocs_Sync_Meta::MIME_* */
    const MIME_DOC = AutoDocs_Sync_Meta::MIME_DOC;
    /** @deprecated Use AutoDocs_Sync_Meta::MIME_* */
    const MIME_FOLDER = AutoDocs_Sync_Meta::MIME_FOLDER;

    /** @var AutoDocs_Settings */
    private $settings;

    /** @var AutoDocs_Google_Client */
    private $google_client;

    /** @var AutoDocs_Sync_Repository */
    private $repository;

    /** @var AutoDocs_Sync_Media */
    private $media;

    /** @var AutoDocs_Sync_Status_Manager */
    private $status;

    /** @var AutoDocs_Sync_Catalog */
    private $catalog;

    /** @var AutoDocs_Sync_Import */
    private $import;

    /** @var AutoDocs_Sync_Engine */
    private $engine;

    public function __construct(AutoDocs_Settings $settings, AutoDocs_Google_Client $google_client)
    {
        $this->settings = $settings;
        $this->google_client = $google_client;

        $this->repository = new AutoDocs_Sync_Repository();
        $this->media = new AutoDocs_Sync_Media($google_client);
        $this->status = new AutoDocs_Sync_Status_Manager($settings, $google_client, $this->repository);
        $this->catalog = new AutoDocs_Sync_Catalog($settings, $google_client, $this->repository, $this->status);
        $this->import = new AutoDocs_Sync_Import($settings, $google_client, $this->repository, $this->media);
        $this->engine = new AutoDocs_Sync_Engine($settings, $google_client, $this->repository, $this->media, $this->status);
    }

    public function sync_all_configured_folders()
    {
        return $this->engine->sync_all_configured_folders();
    }

    public function sync_all($force = false)
    {
        return $this->engine->sync_all($force);
    }

    public function refresh_known_statuses()
    {
        $this->status->refresh_known_statuses();
    }

    public function list_bucket_articles_detailed($bucket_id, $bucket_label = '')
    {
        return $this->catalog->list_bucket_articles_detailed($bucket_id, $bucket_label);
    }

    public function list_modified_articles_from_synced_bucket($bucket_label = '')
    {
        return $this->catalog->list_modified_articles_from_synced_bucket($bucket_label);
    }

    public function count_modified_articles_in_synced_bucket()
    {
        return $this->catalog->count_modified_articles_in_synced_bucket();
    }

    public function sanitize_google_html($html)
    {
        return $this->media->sanitize_google_html($html);
    }

    public function assert_folder_in_new_bucket($folder_id)
    {
        return $this->import->assert_folder_in_new_bucket($folder_id);
    }

    public function assert_folder_in_bucket_by_key($folder_id, $bucket_key)
    {
        return $this->import->assert_folder_in_bucket_by_key($folder_id, $bucket_key);
    }

    public function prepare_new_folder_import($folder_id)
    {
        return $this->import->prepare_new_folder_import($folder_id);
    }

    public function prepare_folder_import($folder_id, $bucket_key = 'new')
    {
        return $this->import->prepare_folder_import($folder_id, $bucket_key);
    }

    public function import_folder_from_drive($folder_id, array $input)
    {
        return $this->import->import_folder_from_drive($folder_id, $input);
    }

    public function import_new_folder_from_drive($folder_id, array $input)
    {
        return $this->import->import_new_folder_from_drive($folder_id, $input);
    }
}
