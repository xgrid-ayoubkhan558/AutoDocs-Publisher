<?php
/**
 * Settings screen markup. Expects variables from AutoDocs_Admin::render_settings_page().
 *
 * @var array<string, mixed> $settings
 * @var bool $connected
 * @var string $client_id
 * @var string $client_secret
 * @var bool $has_saved_creds
 * @var string $oauth_start_url
 * @var string $current_tab
 * @var string $drive_root
 * @var string $fn
 * @var string $fs
 * @var string $root_name
 * @var string $acf_body_field
 * @var array<int, array{value: string, label: string, group: string}> $acf_body_field_choices
 * @var bool $acf_body_use_custom
 * @var string $acf_select_value
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
        <div class="wrap autodocs-settings">
            <div class="autodocs-settings__header">
                <h1 class="autodocs-settings__title"><?php esc_html_e('AutoDocs Publisher', 'autodocs-publisher'); ?></h1>
                <div class="autodocs-settings__connection" aria-label="<?php esc_attr_e('Connection status', 'autodocs-publisher'); ?>">
                    <span id="autodocs-sidebar-status" class="autodocs-settings__connection-status<?php echo $connected ? ' autodocs-sidebar__pill autodocs-sidebar__pill--ok' : ''; ?>"><?php echo $connected ? esc_html__('Connected', 'autodocs-publisher') : esc_html__('Not connected', 'autodocs-publisher'); ?></span>
                    <span class="autodocs-settings__connection-meta">
                        <span class="autodocs-settings__connection-item">
                            <span class="autodocs-settings__connection-label"><?php esc_html_e('Account', 'autodocs-publisher'); ?></span>
                            <span id="autodocs-sidebar-email" class="autodocs-settings__connection-value">&mdash;</span>
                        </span>
                        <span class="autodocs-settings__connection-item">
                            <span class="autodocs-settings__connection-label"><?php esc_html_e('Last synced', 'autodocs-publisher'); ?></span>
                            <span id="autodocs-sidebar-last-sync" class="autodocs-settings__connection-value">&mdash;</span>
                        </span>
                    </span>
                </div>
            </div>
            <?php $this->notice_reconnect_scopes(); ?>
            <?php $this->notice(); ?>

            <form id="autodocs-google-disconnect-form" class="autodocs-hidden-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('autodocs_google_disconnect'); ?>
                <input type="hidden" name="action" value="autodocs_google_disconnect">
            </form>

            <div class="autodocs-settings__layout">
                <form id="autodocs-settings-form" method="post" action="options.php" class="autodocs-settings__form autodocs-settings__primary">
                    <?php settings_fields('autodocs_publisher'); ?>

                    <h2 class="nav-tab-wrapper autodocs-nav-tabs" role="tablist" aria-label="<?php esc_attr_e('Settings sections', 'autodocs-publisher'); ?>">
                        <button type="button" role="tab" class="nav-tab<?php echo 'articles' === $current_tab ? ' nav-tab-active' : ''; ?>" data-autodocs-tab="articles" id="autodocs-tabbtn-articles" aria-controls="autodocs-tab-panel-articles" aria-selected="<?php echo 'articles' === $current_tab ? 'true' : 'false'; ?>"><?php esc_html_e('Articles', 'autodocs-publisher'); ?></button>
                        <button type="button" role="tab" class="nav-tab<?php echo 'drive' === $current_tab ? ' nav-tab-active' : ''; ?>" data-autodocs-tab="drive" id="autodocs-tabbtn-drive" aria-controls="autodocs-tab-panel-drive" aria-selected="<?php echo 'drive' === $current_tab ? 'true' : 'false'; ?>"><?php esc_html_e('Drive & folders', 'autodocs-publisher'); ?></button>
                        <button type="button" role="tab" class="nav-tab<?php echo 'settings' === $current_tab ? ' nav-tab-active' : ''; ?>" data-autodocs-tab="settings" id="autodocs-tabbtn-settings" aria-controls="autodocs-tab-panel-settings" aria-selected="<?php echo 'settings' === $current_tab ? 'true' : 'false'; ?>"><?php esc_html_e('Settings', 'autodocs-publisher'); ?></button>
                    </h2>

                    <div id="autodocs-tab-panel-articles" class="autodocs-tab-panel autodocs-tab-panel--articles<?php echo 'articles' === $current_tab ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="autodocs-tabbtn-articles" tabindex="0">
                        <p class="autodocs-tab-panel__intro description"><?php esc_html_e('Use the New and Synced tabs to browse Drive article folders. The Modified tab lists items in your Synced bucket whose Google Doc changed since the last WordPress import (use Refresh article lists to update). Optional document meta lives between [META START] and [META END] in each Google Doc.', 'autodocs-publisher'); ?></p>
                        <div class="autodocs-sync-summary" id="autodocs-sync-summary">
                            <h3 class="autodocs-sync-summary__title"><?php esc_html_e('Sync summary', 'autodocs-publisher'); ?></h3>
                            <dl class="autodocs-sync-summary__stats" id="autodocs-sidebar-summary">
                                <div class="autodocs-sync-summary__stat">
                                    <dt><?php esc_html_e('New articles', 'autodocs-publisher'); ?></dt>
                                    <dd><span class="autodocs-sidebar__count autodocs-sidebar__count--new" data-autodocs-count="new">&mdash;</span></dd>
                                </div>
                                <div class="autodocs-sync-summary__stat">
                                    <dt><?php esc_html_e('Modified articles', 'autodocs-publisher'); ?></dt>
                                    <dd><span class="autodocs-sidebar__count autodocs-sidebar__count--modified" data-autodocs-count="modified">&mdash;</span></dd>
                                </div>
                                <div class="autodocs-sync-summary__stat">
                                    <dt><?php esc_html_e('Synced articles', 'autodocs-publisher'); ?></dt>
                                    <dd><span class="autodocs-sidebar__count autodocs-sidebar__count--synced" data-autodocs-count="synced">&mdash;</span></dd>
                                </div>
                                <div class="autodocs-sync-summary__stat">
                                    <dt><?php esc_html_e('Total articles', 'autodocs-publisher'); ?></dt>
                                    <dd><span class="autodocs-sidebar__count autodocs-sidebar__count--total" data-autodocs-count="total">&mdash;</span></dd>
                                </div>
                            </dl>
                        </div>
                        <p class="autodocs-tab-panel__toolbar">
                            <button type="button" class="button" id="autodocs-refresh-article-lists"><?php esc_html_e('Refresh article lists', 'autodocs-publisher'); ?></button>
                            <button type="button" class="button" id="autodocs-sync-now"><?php esc_html_e('Sync now', 'autodocs-publisher'); ?></button>
                            <button type="button" class="button button-primary" id="autodocs-open-import-wizard"><?php esc_html_e('Import & Preview', 'autodocs-publisher'); ?></button>
                            <span id="autodocs-sync-now-status" class="autodocs-sync-now-status description" aria-live="polite"></span>
                        </p>
                        <section class="autodocs-recent-syncs" id="autodocs-recent-syncs" aria-labelledby="autodocs-recent-syncs-title">
                            <h3 class="autodocs-recent-syncs__title" id="autodocs-recent-syncs-title"><?php esc_html_e('Recently synced / imported', 'autodocs-publisher'); ?></h3>
                            <p class="description autodocs-recent-syncs__hint"><?php esc_html_e('WordPress posts updated from Drive by manual sync, import, or automatic sync.', 'autodocs-publisher'); ?></p>
                            <ul id="autodocs-recent-syncs-list" class="autodocs-recent-syncs__list" data-initial-recent="1">
                                <?php if (empty($recent_syncs)) : ?>
                                    <li class="description"><?php esc_html_e('No synced posts yet.', 'autodocs-publisher'); ?></li>
                                <?php else : ?>
                                    <?php foreach ($recent_syncs as $row) : ?>
                                        <li class="autodocs-recent-syncs__item">
                                            <?php if (! empty($row['edit_url'])) : ?>
                                                <a href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['title'] ?: __('(no title)', 'autodocs-publisher')); ?></a>
                                            <?php else : ?>
                                                <span><?php echo esc_html($row['title'] ?: __('(no title)', 'autodocs-publisher')); ?></span>
                                            <?php endif; ?>
                                            <span class="autodocs-recent-syncs__meta">
                                                <?php if (! empty($row['sync_source_label'])) : ?>
                                                    <span class="autodocs-recent-syncs__source autodocs-recent-syncs__source--<?php echo esc_attr($row['sync_source']); ?>"><?php echo esc_html($row['sync_source_label']); ?></span>
                                                <?php endif; ?>
                                                <?php if (! empty($row['last_synced_formatted'])) : ?>
                                                    <span class="autodocs-recent-syncs__time"><?php echo esc_html($row['last_synced_formatted']); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </section>
                        <div class="autodocs-articles-subtabs" data-autodocs-article-subtabs>
                            <div class="autodocs-articles-subtabs__nav" role="tablist" aria-label="<?php esc_attr_e('Article bucket', 'autodocs-publisher'); ?>">
                                <button type="button" role="tab" class="autodocs-articles-subtabs__tab is-active" id="autodocs-article-subtab-new" aria-selected="true" aria-controls="autodocs-article-subpanel-new" data-autodocs-article-sub="new"><?php esc_html_e('New', 'autodocs-publisher'); ?></button>
                                <button type="button" role="tab" class="autodocs-articles-subtabs__tab" id="autodocs-article-subtab-synced" aria-selected="false" aria-controls="autodocs-article-subpanel-synced" data-autodocs-article-sub="synced"><?php esc_html_e('Synced', 'autodocs-publisher'); ?></button>
                                <button type="button" role="tab" class="autodocs-articles-subtabs__tab" id="autodocs-article-subtab-modified" aria-selected="false" aria-controls="autodocs-article-subpanel-modified" data-autodocs-article-sub="modified"><?php esc_html_e('Modified', 'autodocs-publisher'); ?></button>
                            </div>
                            <div class="autodocs-articles-shell autodocs-articles-shell--full">
                                <div class="autodocs-articles-shell__main">
                                    <div id="autodocs-article-subpanel-new" class="autodocs-articles-subtabs__panel is-active" role="tabpanel" aria-labelledby="autodocs-article-subtab-new">
                                        <div id="autodocs-article-list-new" class="autodocs-articles-table-wrap" aria-live="polite"></div>
                                    </div>
                                    <div id="autodocs-article-subpanel-synced" class="autodocs-articles-subtabs__panel" role="tabpanel" aria-labelledby="autodocs-article-subtab-synced" hidden>
                                        <div id="autodocs-article-list-synced" class="autodocs-articles-table-wrap" aria-live="polite"></div>
                                    </div>
                                    <div id="autodocs-article-subpanel-modified" class="autodocs-articles-subtabs__panel" role="tabpanel" aria-labelledby="autodocs-article-subtab-modified" hidden>
                                        <div id="autodocs-article-list-modified" class="autodocs-articles-table-wrap" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="autodocs-tab-panel-drive" class="autodocs-tab-panel autodocs-tab-panel--drive<?php echo 'drive' === $current_tab ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="autodocs-tabbtn-drive" tabindex="0">
                        <section class="autodocs-drive-section">
                            <div class="autodocs-drive-section__head">
                                <svg class="autodocs-drive-section__icon autodocs-drive-section__icon--folder" stroke="currentColor" fill="currentColor" stroke-width="0" version="1" enable-background="new 0 0 48 48" xmlns="http://www.w3.org/2000/svg" viewBox="4 8 42.18 32">
                                        <path fill="#FFA000" d="M38,12H22l-4-4H8c-2.2,0-4,1.8-4,4v24c0,2.2,1.8,4,4,4h31c1.7,0,3-1.3,3-3V16C42,13.8,40.2,12,38,12z"></path><path fill="#FFCA28" d="M42.2,18H15.3c-1.9,0-3.6,1.4-3.9,3.3L8,40h31.7c1.9,0,3.6-1.4,3.9-3.3l2.5-14C46.6,20.3,44.7,18,42.2,18z"></path>
                                    </svg>
                                <div>
                                    <h3 class="autodocs-drive-section__title"><?php esc_html_e('Drive root folder', 'autodocs-publisher'); ?></h3>
                                    <p class="autodocs-drive-section__subtitle"><?php esc_html_e('This is the main folder that contains your article buckets.', 'autodocs-publisher'); ?></p>
                                </div>
                            </div>
                            <div class="autodocs-drive-root autodocs-drive-root--carded">
                                <div class="autodocs-drive-root__card">
                                    <div class="autodocs-drive-root__row">
                                        <div class="autodocs-drive-root__meta">
                                            <svg class="autodocs-drive-root__folder-ico" stroke="currentColor" fill="currentColor" stroke-width="0" xmlns="http://www.w3.org/2000/svg" viewBox="32 96 448 320"><path d="M437.334 144H256.006l-42.668-48H74.666C51.197 96 32 115.198 32 138.667v234.666C32 396.802 51.197 416 74.666 416h362.668C460.803 416 480 396.802 480 373.333V186.667C480 163.198 460.803 144 437.334 144zM448 373.333c0 5.782-4.885 10.667-10.666 10.667H74.666C68.884 384 64 379.115 64 373.333V176h373.334c5.781 0 10.666 4.885 10.666 10.667v186.666z"></path></svg>
                                            <div>
                                                <strong class="autodocs-drive-root__name" id="autodocs-drive-root-name"><?php echo $root_name !== '' ? esc_html($root_name) : esc_html__('— No folder selected —', 'autodocs-publisher'); ?></strong>
                                                <div class="autodocs-drive-root__path" id="autodocs-drive-root-path"><?php echo $root_name !== '' ? esc_html('Drive / ' . $root_name) : esc_html__('Drive / —', 'autodocs-publisher'); ?></div>
                                                <code class="screen-reader-text" id="autodocs-drive-root-id-display"><?php echo $drive_root !== '' ? esc_html($drive_root) : '—'; ?></code>
                                            </div>
                                        </div>
                                        <button type="button" class="button autodocs-drive-root__browse" id="autodocs-browse-drive-folders"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e('Browse Drive', 'autodocs-publisher'); ?></button>
                                    </div>
                                    <input type="hidden" id="autodocs-working-folder-name" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[working_folder_name]" value="<?php echo esc_attr($root_name); ?>">
                                    <label class="screen-reader-text" for="autodocs-working-folder-id"><?php esc_html_e('Drive root folder ID', 'autodocs-publisher'); ?></label>
                                    <input type="hidden" id="autodocs-working-folder-id" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[working_folder_id]" value="<?php echo esc_attr(isset($settings['working_folder_id']) ? $settings['working_folder_id'] : ''); ?>" autocomplete="off">
                                </div>
                                <div id="autodocs-drive-folder-picker" class="autodocs-drive-folder-picker autodocs-drive-root__picker">
                                    <div class="autodocs-drive-folder-picker__toolbar">
                                        <button type="button" class="button" id="autodocs-drive-folder-up" disabled><?php esc_html_e('Up', 'autodocs-publisher'); ?></button>
                                        <span id="autodocs-drive-folder-crumb" class="autodocs-drive-folder-picker__crumb"></span>
                                    </div>
                                    <div id="autodocs-drive-folder-list" class="autodocs-drive-folder-list" aria-live="polite"></div>
                                    <p class="description" id="autodocs-drive-folder-msg"></p>
                                </div>
                            </div>
                            <div class="autodocs-drive-info" role="note">
                                <p class="autodocs-drive-info__title"><?php esc_html_e('How folders work', 'autodocs-publisher'); ?></p>
                                <ul class="autodocs-drive-info__list">
                                    <li><?php esc_html_e('The root can contain buckets: New (manual import) and Synced (live sync). The Modified tab is not a Drive folder — it shows Synced articles with updated Google Docs.', 'autodocs-publisher'); ?></li>
                                    <li><?php esc_html_e('If buckets are set, articles are read from inside them.', 'autodocs-publisher'); ?></li>
                                    <li><?php esc_html_e('If no buckets are set, each subfolder in the root can be treated as an article.', 'autodocs-publisher'); ?></li>
                                </ul>
                            </div>
                        </section>

                        <p class="description autodocs-buckets-hint" id="autodocs-buckets-hidden-hint"<?php echo $drive_root ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Select a Drive root above to choose bucket folders. Buckets must be direct subfolders of that root.', 'autodocs-publisher'); ?></p>

                        <div id="autodocs-drive-buckets-wrap" class="autodocs-drive-buckets-wrap"<?php echo $drive_root ? '' : ' style="display:none;"'; ?>>
                            <section class="autodocs-drive-section autodocs-drive-section--buckets">
                                <div class="autodocs-drive-section__head">
                                    <svg class="autodocs-drive-section__icon autodocs-drive-section__icon--doc" stroke="currentColor" fill="currentColor" stroke-width="0" version="1" enable-background="new 0 0 48 48" xmlns="http://www.w3.org/2000/svg" viewBox="4 8 42.18 32">
                                        <path fill="#FFA000" d="M38,12H22l-4-4H8c-2.2,0-4,1.8-4,4v24c0,2.2,1.8,4,4,4h31c1.7,0,3-1.3,3-3V16C42,13.8,40.2,12,38,12z"></path><path fill="#FFCA28" d="M42.2,18H15.3c-1.9,0-3.6,1.4-3.9,3.3L8,40h31.7c1.9,0,3.6-1.4,3.9-3.3l2.5-14C46.6,20.3,44.7,18,42.2,18z"></path>
                                    </svg>
                                    <div>
                                        <h3 class="autodocs-drive-section__title"><?php esc_html_e('Article buckets', 'autodocs-publisher'); ?></h3>
                                        <p class="autodocs-drive-section__subtitle"><?php esc_html_e('Each option is a direct child of the Drive root.', 'autodocs-publisher'); ?></p>
                                    </div>
                                </div>
                                <div class="autodocs-bucket-cards">
                                    <div class="autodocs-bucket-card autodocs-bucket-card--new">
                                        <div class="autodocs-bucket-card__head">
                                            <svg class="autodocs-bucket-card__icon" stroke="currentColor" fill="currentColor" stroke-width="0" xmlns="http://www.w3.org/2000/svg" viewBox="2.06 2.06 19.87 19.87"><g id="Circle_Plus"><g><path d="M15,12.5H12.5V15a.5.5,0,0,1-1,0V12.5H9a.5.5,0,0,1,0-1h2.5V9a.5.5,0,0,1,1,0v2.5H15A.5.5,0,0,1,15,12.5Z"></path><path d="M12,21.932A9.934,9.934,0,1,1,21.932,12,9.944,9.944,0,0,1,12,21.932ZM12,3.065A8.934,8.934,0,1,0,20.932,12,8.944,8.944,0,0,0,12,3.065Z"></path></g></g></svg>
                                            <div class="autodocs-bucket-card__head-content">
                                                <h4 class="autodocs-bucket-card__title"><?php esc_html_e('New', 'autodocs-publisher'); ?></h4>
                                                <p class="autodocs-bucket-card__desc"><?php esc_html_e('New articles waiting to be imported.', 'autodocs-publisher'); ?></p>
                                            </div>
                                        </div>
                                        <label class="screen-reader-text" for="autodocs-bucket-new"><?php esc_html_e('New bucket folder', 'autodocs-publisher'); ?></label>
                                        <select class="autodocs-bucket-select" id="autodocs-bucket-new" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[folder_new]" data-autodocs-saved="<?php echo esc_attr($fn); ?>">
                                            <option value=""><?php esc_html_e('— Select folder —', 'autodocs-publisher'); ?></option>
                                        </select>
                                        <p class="autodocs-bucket-card__actions"><button type="button" class="button-link autodocs-bucket-card__change" data-autodocs-focus-select="autodocs-bucket-new"><?php esc_html_e('Change folder', 'autodocs-publisher'); ?></button></p>
                                    </div>
                                    <div class="autodocs-bucket-card autodocs-bucket-card--synced">
                                        <div class="autodocs-bucket-card__head">
                                            <svg class="autodocs-bucket-card__icon" stroke="currentColor" fill="currentColor" stroke-width="0" xmlns="http://www.w3.org/2000/svg" viewBox="48 48 416 416"><path fill="none" stroke-miterlimit="10" stroke-width="32" d="M448 256c0-106-86-192-192-192S64 150 64 256s86 192 192 192 192-86 192-192z"></path><path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M352 176 217.6 336 160 272"></path></svg>
                                            <div class="autodocs-bucket-card__head-content">
                                                <h4 class="autodocs-bucket-card__title"><?php esc_html_e('Synced', 'autodocs-publisher'); ?></h4>
                                                <p class="autodocs-bucket-card__desc"><?php esc_html_e('Articles already imported successfully.', 'autodocs-publisher'); ?></p>
                                            </div>
                                        </div>
                                        <label class="screen-reader-text" for="autodocs-bucket-synced"><?php esc_html_e('Synced bucket folder', 'autodocs-publisher'); ?></label>
                                        <select class="autodocs-bucket-select" id="autodocs-bucket-synced" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[folder_synced]" data-autodocs-saved="<?php echo esc_attr($fs); ?>">
                                            <option value=""><?php esc_html_e('— Select folder —', 'autodocs-publisher'); ?></option>
                                        </select>
                                        <p class="autodocs-bucket-card__actions"><button type="button" class="button-link autodocs-bucket-card__change" data-autodocs-focus-select="autodocs-bucket-synced"><?php esc_html_e('Change folder', 'autodocs-publisher'); ?></button></p>
                                    </div>
                                </div>
                                <p class="description" id="autodocs-bucket-msg"></p>
                            </section>
                        </div>
                    </div>

                    <div id="autodocs-tab-panel-settings" class="autodocs-tab-panel autodocs-tab-panel--settings<?php echo 'settings' === $current_tab ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="autodocs-tabbtn-settings" tabindex="0">
                    <section
                        class="autodocs-cron-settings"
                        id="autodocs-cron-settings"
                        data-timezone="<?php echo esc_attr(AutoDocs_Cron::timezone_for_intl()); ?>"
                        data-timezone-label="<?php echo esc_attr($cron_timezone_label); ?>"
                        data-gmt-offset="<?php echo esc_attr((string) AutoDocs_Cron::site_gmt_offset_hours()); ?>"
                        data-next-ts="<?php echo esc_attr((string) AutoDocs_Cron::next_run_timestamp()); ?>"
                    >
                        <h3 class="autodocs-cron-settings__title"><?php esc_html_e('Automatic sync', 'autodocs-publisher'); ?></h3>
                        <p class="description"><?php esc_html_e('Periodically refresh modified status and update posts in your Synced bucket from Google Drive. New bucket articles are still imported manually.', 'autodocs-publisher'); ?></p>
                        <p>
                            <label>
                                <input type="checkbox" id="autodocs-cron-enabled" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[cron_enabled]" value="1" <?php checked($cron_enabled); ?> />
                                <?php esc_html_e('Enable automatic sync', 'autodocs-publisher'); ?>
                            </label>
                        </p>
                        <p class="autodocs-cron-settings__row">
                            <label for="autodocs-cron-interval"><?php esc_html_e('Run every', 'autodocs-publisher'); ?></label>
                            <select id="autodocs-cron-interval" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[cron_interval]">
                                <?php foreach (AutoDocs_Cron::interval_labels() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($cron_interval, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <ul class="autodocs-cron-settings__clocks description">
                            <li>
                                <strong><?php esc_html_e('Your computer time now:', 'autodocs-publisher'); ?></strong>
                                <span id="autodocs-cron-computer-now" aria-live="polite">&mdash;</span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('WordPress site time now:', 'autodocs-publisher'); ?></strong>
                                <span id="autodocs-cron-site-now"><?php echo esc_html($cron_site_time_now); ?></span>
                                <a href="<?php echo esc_url($cron_general_settings_url); ?>"><?php esc_html_e('Change timezone', 'autodocs-publisher'); ?></a>
                            </li>
                        </ul>
                        <p class="notice notice-warning autodocs-cron-settings__tz-warn" id="autodocs-cron-tz-warn" hidden></p>
                        <p class="autodocs-cron-settings__row autodocs-cron-settings__row--time" id="autodocs-cron-time-row"<?php echo $cron_show_time ? '' : ' hidden'; ?>>
                            <label for="autodocs-cron-time"><?php esc_html_e('Time of day (site time)', 'autodocs-publisher'); ?></label>
                            <input
                                type="time"
                                id="autodocs-cron-time"
                                name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[cron_time]"
                                value="<?php echo esc_attr($cron_time); ?>"
                                step="60"
                            />
                            <span class="description autodocs-cron-settings__tz"><?php echo esc_html(sprintf(__('Uses %s — not your computer clock.', 'autodocs-publisher'), $cron_timezone_label)); ?></span>
                        </p>
                        <p class="description autodocs-cron-settings__schedule" id="autodocs-cron-schedule-summary"><?php echo esc_html($cron_schedule_description); ?></p>
                        <p class="description autodocs-cron-settings__save-hint"><?php esc_html_e('Save settings to apply interval or time changes to the WordPress schedule.', 'autodocs-publisher'); ?></p>
                        <ul class="autodocs-cron-settings__meta description">
                            <li class="autodocs-cron-settings__next" id="autodocs-cron-next-block">
                                <p class="autodocs-cron-settings__next-line">
                                    <strong><?php esc_html_e('Next run (site time):', 'autodocs-publisher'); ?></strong>
                                    <span id="autodocs-cron-next-run"><?php echo $cron_enabled && $cron_next_run !== '' ? esc_html($cron_next_run) : esc_html__('Not scheduled (save settings after enabling)', 'autodocs-publisher'); ?></span>
                                    <?php if ($cron_next_run_relative !== '') : ?>
                                        <span class="autodocs-cron-settings__relative" id="autodocs-cron-next-run-relative"><?php echo esc_html($cron_next_run_relative); ?></span>
                                    <?php else : ?>
                                        <span class="autodocs-cron-settings__relative" id="autodocs-cron-next-run-relative" hidden></span>
                                    <?php endif; ?>
                                </p>
                                <p class="autodocs-cron-settings__next-line" id="autodocs-cron-next-local-row"<?php echo ($cron_enabled && $cron_next_run_ts > 0) ? '' : ' hidden'; ?>>
                                    <strong><?php esc_html_e('On your computer:', 'autodocs-publisher'); ?></strong>
                                    <span id="autodocs-cron-next-run-local"></span>
                                    <span class="autodocs-cron-settings__relative" id="autodocs-cron-next-run-local-relative"></span>
                                </p>
                                <p class="description autodocs-cron-settings__next-hint" id="autodocs-cron-next-run-hint"></p>
                                <p class="description autodocs-cron-settings__repeat" id="autodocs-cron-repeat-label"><?php echo esc_html($cron_repeat_label); ?></p>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Last automatic run:', 'autodocs-publisher'); ?></strong>
                                <span id="autodocs-cron-last-run"><?php echo $cron_last_run !== '' ? esc_html($cron_last_run) : esc_html__('Never', 'autodocs-publisher'); ?></span>
                            </li>
                        </ul>
                    </section>
                    <div class="autodocs-settings__api-card autodocs-api-card <?php echo $connected ? 'autodocs-api-card--ok' : 'autodocs-api-card--off'; ?>">
                        <div class="autodocs-api-card__top">
                            <div class="autodocs-api-card__main">
                                <span class="autodocs-api-card__badge" aria-hidden="true"></span>
                                <div>
                                    <strong class="autodocs-api-card__title">
                                        <?php echo $connected ? esc_html__('Google API: Connected', 'autodocs-publisher') : esc_html__('Google API: Not connected', 'autodocs-publisher'); ?>
                                    </strong>
                                    <p class="autodocs-api-card__hint">
                                        <?php
                                        if ($connected) {
                                            esc_html_e('Drive and Docs access is authorized. Use Disconnect Drive below to revoke tokens. You can update credentials here.', 'autodocs-publisher');
                                        } elseif ($has_saved_creds) {
                                            esc_html_e('Credentials are saved. Click Connect to sign in with Google. After changing ID or secret, save settings first.', 'autodocs-publisher');
                                        } else {
                                            esc_html_e('Enter your Google OAuth Client ID and Client Secret here, then save settings. Connect appears once both fields are saved.', 'autodocs-publisher');
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <div class="autodocs-api-card__actions">
                                <?php if ($connected) : ?>
                                    <button type="submit" class="button" form="autodocs-google-disconnect-form" onclick="return window.confirm('<?php echo esc_js(__('Disconnect Google Drive from this site?', 'autodocs-publisher')); ?>');">
                                        <?php esc_html_e('Disconnect Drive', 'autodocs-publisher'); ?>
                                    </button>
                                <?php elseif ($has_saved_creds && $oauth_start_url !== '') : ?>
                                    <a class="button button-primary" href="<?php echo esc_url($oauth_start_url); ?>"><?php esc_html_e('Connect Google Drive', 'autodocs-publisher'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="autodocs-google-creds">
                            <p class="autodocs-google-creds__field">
                                <label for="autodocs-client-id"><?php esc_html_e('Google Client ID', 'autodocs-publisher'); ?></label>
                                <input class="large-text" id="autodocs-client-id" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[client_id]" value="<?php echo esc_attr($client_id); ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
                            </p>
                            <p class="autodocs-google-creds__field">
                                <label for="autodocs-client-secret"><?php esc_html_e('Google Client Secret', 'autodocs-publisher'); ?></label>
                                <input class="large-text" id="autodocs-client-secret" type="password" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[client_secret]" value="<?php echo esc_attr($client_secret); ?>" autocomplete="off">
                            </p>
                        </div>
                    </div>
                    </div>

                    <div class="autodocs-settings-form-footer">
                        <div class="autodocs-settings-form-footer__right">
                            <?php submit_button(__('Save changes', 'autodocs-publisher'), 'primary', 'submit', false, array('id' => 'autodocs-save-settings')); ?>
                        </div>
                    </div>
                </form>

            </div>
            <?php include AUTODOCS_PUBLISHER_DIR . 'includes/admin/views/import-wizard.php'; ?>
        </div>
