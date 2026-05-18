<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Localized script payloads for the admin UI (keeps enqueue code small).
 */
final class AutoDocs_Admin_Localization
{
    /**
     * @return array<string, mixed>
     */
    public static function publisher_config(AutoDocs_Settings $settings)
    {
        $authors = array();
        $users = get_users(
            array(
                'capability' => 'edit_posts',
                'number' => 100,
                'orderby' => 'display_name',
            )
        );
        foreach ($users as $user) {
            $authors[] = array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
            );
        }

        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('autodocs_sync_now'),
            'articlesPerPage' => 20,
            'importNonce' => wp_create_nonce('autodocs_import'),
            'oauthNonce' => wp_create_nonce('autodocs_google_oauth'),
            'redirectUri' => $settings->redirect_uri(),
            'currentUserId' => get_current_user_id(),
            'authors' => $authors,
            'acfImportDefaults' => AutoDocs_Acf_Helpers::import_body_field_map(),
            'acfSelectCustomValue' => AutoDocs_Acf_Helpers::SELECT_CUSTOM_VALUE,
            'i18n' => array(
                'browseDrive' => __('Browse Drive…', 'autodocs-publisher'),
                'up' => __('Up', 'autodocs-publisher'),
                'open' => __('Open', 'autodocs-publisher'),
                'selectFolder' => __('Use as Drive root folder', 'autodocs-publisher'),
                'loadingFolders' => __('Loading folders…', 'autodocs-publisher'),
                'noFolders' => __('No subfolders here.', 'autodocs-publisher'),
                'folderListError' => __('Could not load folders.', 'autodocs-publisher'),
                'myDrive' => __('My Drive', 'autodocs-publisher'),
                'selectBucketPlaceholder' => __('— Select folder —', 'autodocs-publisher'),
                'loadingArticleFolders' => __('Loading article folders…', 'autodocs-publisher'),
                'noArticleFolders' => __('No article folders in this bucket.', 'autodocs-publisher'),
                'articleFoldersHint' => __('Subfolders here are treated as articles (each should contain a Google Doc and optional featured image).', 'autodocs-publisher'),
                'setDriveRootFirst' => __('Set a Drive root folder above to load bucket options.', 'autodocs-publisher'),
                'articlesTabPickBuckets' => __('Choose bucket folders on the Drive & folders tab to list articles here.', 'autodocs-publisher'),
                'import' => __('Import', 'autodocs-publisher'),
                'savePost' => __('Save as WordPress post', 'autodocs-publisher'),
                'cancel' => __('Cancel', 'autodocs-publisher'),
                'moveToSynced' => __('Move folder to Synced in Drive after save', 'autodocs-publisher'),
                'metaFromDoc' => __('Meta from document', 'autodocs-publisher'),
                'contentPreview' => __('Content preview', 'autodocs-publisher'),
                'preparingImport' => __('Loading import options…', 'autodocs-publisher'),
                'postTitle' => __('Post title', 'autodocs-publisher'),
                'postSlug' => __('URL slug', 'autodocs-publisher'),
                'postType' => __('Post type', 'autodocs-publisher'),
                'postStatus' => __('Status', 'autodocs-publisher'),
                'excerpt' => __('Excerpt', 'autodocs-publisher'),
                'importBodyTargetLabel' => __('Imported HTML goes to', 'autodocs-publisher'),
                'importBodyTargetAcf' => __('ACF field', 'autodocs-publisher'),
                'importBodyTargetOtherShort' => __('Other (custom field key)', 'autodocs-publisher'),
                'importBodyTargetAcfEmpty' => __('No WYSIWYG, textarea, or code fields are available for this post type.', 'autodocs-publisher'),
                'importPostContentOnly' => __('Post content (editor) only', 'autodocs-publisher'),
                'importUseSettingsDefault' => __('Use plugin Settings default', 'autodocs-publisher'),
                'importAcfOther' => __('Other field key or name…', 'autodocs-publisher'),
                'importAcfOtherPlaceholder' => __('ACF field key or name', 'autodocs-publisher'),
                'importAcfBodyHint' => __('First option saves HTML to the post editor. Then choose an ACF WYSIWYG, textarea, or code field, or Other to type a field key.', 'autodocs-publisher'),
                'acfFieldGroup' => __('Field group', 'autodocs-publisher'),
                'categoriesLabel' => __('Categories', 'autodocs-publisher'),
                'tagsLabel' => __('Tags', 'autodocs-publisher'),
                'categoriesHint' => __('Hold Ctrl (Windows) or Command (Mac) to select multiple.', 'autodocs-publisher'),
                'existingPostNotice' => __('This folder is already linked to a post; saving will update that post.', 'autodocs-publisher'),
                'importFailed' => __('Import failed.', 'autodocs-publisher'),
                'importSuccess' => __('Post saved.', 'autodocs-publisher'),
                'editPost' => __('Edit post', 'autodocs-publisher'),
                'article' => __('Article', 'autodocs-publisher'),
                'driveFolder' => __('Drive folder', 'autodocs-publisher'),
                'lastModified' => __('Last modified', 'autodocs-publisher'),
                'size' => __('Size', 'autodocs-publisher'),
                'actions' => __('Actions', 'autodocs-publisher'),
                'openInDrive' => __('Open in Drive', 'autodocs-publisher'),
                'selectRow' => __('Select', 'autodocs-publisher'),
                'previewThumb' => __('Featured preview', 'autodocs-publisher'),
                'categoryColumn' => __('Categories (from doc)', 'autodocs-publisher'),
                'lastSynced' => __('Last synced', 'autodocs-publisher'),
                'categoriesFromDoc' => __('Use categories from document meta', 'autodocs-publisher'),
                'categoriesManual' => __('Choose WordPress categories below', 'autodocs-publisher'),
                'tagsFromDoc' => __('Use tags from document meta', 'autodocs-publisher'),
                'tagsManual' => __('Enter tags manually (comma-separated)', 'autodocs-publisher'),
                'docCategoriesFromMeta' => __('From document (will be applied on save)', 'autodocs-publisher'),
                'docTagsFromMeta' => __('From document (will be applied on save)', 'autodocs-publisher'),
                'docMetaListEmpty' => __('None listed in document meta for this field.', 'autodocs-publisher'),
                'taxExistsInWp' => __('Already in WordPress', 'autodocs-publisher'),
                'taxWillCreate' => __('Will be created on import', 'autodocs-publisher'),
                'taxLegend' => __('Green = exists in WordPress. Amber = will be created on import.', 'autodocs-publisher'),
                'articlesPagination' => __('Article list pages', 'autodocs-publisher'),
                'prevPage' => __('Previous', 'autodocs-publisher'),
                'nextPage' => __('Next', 'autodocs-publisher'),
                'pageOf' => __('Page %1$s of %2$s (%3$s articles)', 'autodocs-publisher'),
                'wizardTitle' => __('Import & Preview', 'autodocs-publisher'),
                'wizardBack' => __('Back to Articles', 'autodocs-publisher'),
                'wizardStepSelect' => __('Select Articles', 'autodocs-publisher'),
                'wizardStepPreview' => __('Preview & Configure', 'autodocs-publisher'),
                'wizardStepConfirm' => __('Confirm & Import', 'autodocs-publisher'),
                'wizardSelected' => __('Selected Articles', 'autodocs-publisher'),
                'wizardClearAll' => __('Clear All', 'autodocs-publisher'),
                'wizardSearch' => __('Search selected…', 'autodocs-publisher'),
                'wizardContinue' => __('Continue to Preview', 'autodocs-publisher'),
                'wizardChangeArticle' => __('Change article', 'autodocs-publisher'),
                'wizardTabContent' => __('Content Preview', 'autodocs-publisher'),
                'wizardTabMeta' => __('Metadata (META)', 'autodocs-publisher'),
                'wizardTabImages' => __('Images & Files', 'autodocs-publisher'),
                'wizardTabDrive' => __('Drive Info', 'autodocs-publisher'),
                'wizardImportOptions' => __('Import Options', 'autodocs-publisher'),
                'wizardAuthor' => __('Author', 'autodocs-publisher'),
                'wizardCategoryAssignment' => __('Category assignment', 'autodocs-publisher'),
                'wizardTagsAssignment' => __('Tags assignment', 'autodocs-publisher'),
                'wizardFeaturedImage' => __('Featured image', 'autodocs-publisher'),
                'wizardFromMeta' => __('From document (META)', 'autodocs-publisher'),
                'wizardManualCategories' => __('Choose WordPress categories', 'autodocs-publisher'),
                'wizardManualTags' => __('Enter tags manually', 'autodocs-publisher'),
                'wizardUseDocImage' => __('Use image from document', 'autodocs-publisher'),
                'wizardImportExcerpt' => __('Import excerpt', 'autodocs-publisher'),
                'wizardImportExcerptHint' => __('Use excerpt from META or document.', 'autodocs-publisher'),
                'wizardMoveSynced' => __('Move folder to Synced after import', 'autodocs-publisher'),
                'wizardMetaDetected' => __('META detected in document. Title, excerpt, categories and other settings will be imported from the document\'s META block.', 'autodocs-publisher'),
                'wizardCancel' => __('Cancel', 'autodocs-publisher'),
                'wizardImportN' => __('Import %s Articles', 'autodocs-publisher'),
                'wizardImportOne' => __('Import Article', 'autodocs-publisher'),
                'wizardOpen' => __('Import & Preview', 'autodocs-publisher'),
                'wizardSelectHint' => __('Select articles from the list below, then continue to preview and configure import settings.', 'autodocs-publisher'),
                'wizardConfirmHint' => __('Review your selection and import settings, then import to WordPress.', 'autodocs-publisher'),
                'wizardImporting' => __('Importing…', 'autodocs-publisher'),
                'wizardDone' => __('Import complete.', 'autodocs-publisher'),
                'wizardDocStats' => __('Document stats', 'autodocs-publisher'),
                'wizardNoFiles' => __('No additional files in this folder.', 'autodocs-publisher'),
                'wizardNoMeta' => __('No META block found in this document.', 'autodocs-publisher'),
                'wizardSelectOne' => __('Select at least one article.', 'autodocs-publisher'),
                'wizardOfSelected' => __('%1$s of %2$s selected', 'autodocs-publisher'),
                'wizardNewBadge' => __('New', 'autodocs-publisher'),
                'wizardSelectAll' => __('Select all on this page', 'autodocs-publisher'),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function dashboard_config(AutoDocs_Settings $settings, AutoDocs_Google_Client $google_client)
    {
        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('autodocs_sync_now'),
            'connected' => $google_client->is_connected(),
            'needsReconnect' => $google_client->tokens_need_reconnect_for_scopes(),
            'workingRootName' => (string) $settings->get('working_folder_name', ''),
            'workingRootId' => (string) $settings->get('working_folder_id', ''),
            'i18n' => array(
                'connectionStatus' => __('Connection status', 'autodocs-publisher'),
                'connected' => __('Connected', 'autodocs-publisher'),
                'notConnected' => __('Not connected', 'autodocs-publisher'),
                'account' => __('Account', 'autodocs-publisher'),
                'rootFolder' => __('Drive root', 'autodocs-publisher'),
                'buckets' => __('Buckets', 'autodocs-publisher'),
                'summary' => __('Sync summary', 'autodocs-publisher'),
                'newCount' => __('New folders', 'autodocs-publisher'),
                'syncedCount' => __('Synced folders', 'autodocs-publisher'),
                'modifiedCount' => __('Modified folders', 'autodocs-publisher'),
                'reconnectHint' => __('Disconnect Google under Settings, then connect again to refresh Drive permissions.', 'autodocs-publisher'),
                'importAsideTitle' => __('Import', 'autodocs-publisher'),
                'importAsideEmpty' => __('Select a row in the New table to configure import.', 'autodocs-publisher'),
                'selectRow' => __('Select', 'autodocs-publisher'),
                'article' => __('Article', 'autodocs-publisher'),
                'driveFolder' => __('Drive folder', 'autodocs-publisher'),
                'lastModified' => __('Last modified', 'autodocs-publisher'),
                'size' => __('Size', 'autodocs-publisher'),
                'actions' => __('Actions', 'autodocs-publisher'),
                'openInDrive' => __('Open in Drive', 'autodocs-publisher'),
                'previewThumb' => __('Featured preview', 'autodocs-publisher'),
            ),
        );
    }
}
